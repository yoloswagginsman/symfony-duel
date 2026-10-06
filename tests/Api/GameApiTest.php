<?php

namespace App\Tests\Api;

use App\Entity\Deck;
use App\Entity\User;
use App\Tests\Support\RecordingHub;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * API партий целиком: JWT, создание, присоединение, ходы, ошибки.
 * База — cards_test с загруженным контентом (README: «Тесты»). Пользователи создаются и удаляются в тесте.
 */
final class GameApiTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /** @var list<User> */
    private array $users = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        if ($this->entityManager->getRepository(Deck::class)->count(['owner' => null]) === 0) {
            self::markTestSkipped('В cards_test нет базовых колод: APP_ENV=test php bin/console app:content:import');
        }
    }

    protected function tearDown(): void
    {
        // Партии игроков удалятся каскадом
        foreach ($this->users as $user) {
            $this->entityManager->remove($this->entityManager->find(User::class, $user->getId()));
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testFullGameFlow(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $deckId = $this->baseDeckId();

        $created = $this->request($alice, 'POST', '/api/games', ['deckId' => $deckId]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('waiting', $created['game']['status']);
        $gameId = $created['game']['id'];

        $list = $this->request($bob, 'GET', '/api/games');
        self::assertContains($gameId, array_column($list['waiting'], 'id'));

        $joined = $this->request($bob, 'POST', "/api/games/{$gameId}/join", ['deckId' => $deckId]);
        self::assertResponseIsSuccessful();
        self::assertSame('active', $joined['game']['status']);
        self::assertSame(1, $joined['state']['turn']);

        // Кто ходит первым — жребий
        $first = $joined['game']['players'][0]['id'] === $alice->getId() ? $alice : $bob;
        $second = $first === $alice ? $bob : $alice;

        $this->request($second, 'POST', "/api/games/{$gameId}/actions", ['type' => 'end_turn']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Сейчас ход другого игрока.', $this->response()['error']);

        $turn = $this->request($first, 'POST', "/api/games/{$gameId}/actions", ['type' => 'end_turn']);
        self::assertResponseIsSuccessful();
        self::assertSame(['TurnEnded', 'TurnStarted', 'CardDrawn'], array_column($turn['events'], 'type'));
        self::assertNull($turn['events'][2]['card'], 'карту, которую взял противник, не видно');
        self::assertSame(1, $turn['state']['activePlayer']);

        $view = $this->request($second, 'GET', "/api/games/{$gameId}");
        self::assertCount(5, $view['state']['players'][1]['hand'], '4 стартовых + 1 взятая');
        self::assertNull($view['state']['players'][0]['hand'], 'руку противника не видно');
        self::assertSame(3, $view['game']['version'], 'создание, старт, ход');
    }

    public function testStrangerHasNoAccess(): void
    {
        [$alice, $bob, $eve] = [$this->user('alice'), $this->user('bob'), $this->user('eve')];
        $gameId = $this->startedGame($alice, $bob);

        $this->request($eve, 'GET', "/api/games/{$gameId}");
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Вы не участвуете в этой партии.', $this->response()['error']);
    }

    public function testCannotJoinOwnOrStartedGame(): void
    {
        [$alice, $bob, $eve] = [$this->user('alice'), $this->user('bob'), $this->user('eve')];
        $created = $this->request($alice, 'POST', '/api/games', ['deckId' => $this->baseDeckId()]);

        $this->request($alice, 'POST', "/api/games/{$created['game']['id']}/join", ['deckId' => $this->baseDeckId()]);
        self::assertResponseStatusCodeSame(409);

        $gameId = $this->startedGame($alice, $bob);
        $this->request($eve, 'POST', "/api/games/{$gameId}/join", ['deckId' => $this->baseDeckId()]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Партия уже началась.', $this->response()['error']);
    }

    public function testBadRequests(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);

        $this->request($alice, 'POST', "/api/games/{$gameId}/actions", ['type' => 'attack']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['attackerId' => 'Для attack укажите attackerId (число).'], $this->response()['violations']);

        $this->request($alice, 'POST', '/api/games', ['deckId' => 999999]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', "/api/games/{$gameId}/actions", server: $this->headers($alice), content: 'не json');
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/api/games');
        self::assertResponseStatusCodeSame(401);
    }

    public function testPlayersGetOwnRealtimeUpdates(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);

        $updates = static::getContainer()->get(RecordingHub::class)->updates;
        self::assertCount(2, $updates, 'старт — каждому участнику своё событие');

        foreach ([$alice, $bob] as $user) {
            $update = array_values(array_filter(
                $updates,
                static fn ($update) => $update->getTopics() === [sprintf('/users/%d/games/%d', $user->getId(), $gameId)],
            ))[0];
            $data = json_decode($update->getData(), true);
            $you = $data['state']['you'];

            self::assertTrue($update->isPrivate());
            self::assertSame($user->getId(), $data['game']['players'][$you]['id']);
            self::assertIsArray($data['state']['players'][$you]['hand'], 'своя рука видна');
            self::assertNull($data['state']['players'][1 - $you]['hand'], 'чужая — нет');
        }
    }

    public function testSubscriptionOnlyForParticipants(): void
    {
        [$alice, $bob, $eve] = [$this->user('alice'), $this->user('bob'), $this->user('eve')];
        $gameId = $this->startedGame($alice, $bob);

        $subscription = $this->request($alice, 'GET', "/api/games/{$gameId}/subscription");

        $topic = sprintf('/users/%d/games/%d', $alice->getId(), $gameId);
        self::assertSame($topic, $subscription['topic']);
        self::assertStringEndsWith('/.well-known/mercure', $subscription['hub']);
        $payload = json_decode(base64_decode(strtr(explode('.', $subscription['token'])[1], '-_', '+/')), true);
        self::assertSame((string) $alice->getId(), $payload['sub']);
        self::assertStringContainsString($topic, json_encode($payload['authorization_details'], \JSON_UNESCAPED_SLASHES));

        $this->request($eve, 'GET', "/api/games/{$gameId}/subscription");
        self::assertResponseStatusCodeSame(403);
    }

    public function testGameAgainstComputer(): void
    {
        $alice = $this->user('alice');

        $started = $this->request($alice, 'POST', '/api/games/computer', ['deckId' => $this->baseDeckId()]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('active', $started['game']['status']);

        $you = $started['state']['you'];
        $computer = 1 - $you;
        self::assertSame(['id' => null, 'nickname' => 'Компьютер', 'computer' => true], $started['game']['players'][$computer]);
        self::assertSame($you, $started['state']['activePlayer'], 'компьютер, если ходил первым, уже сыграл — ход за игроком');

        // Конец хода — компьютер сразу играет свой, и ход снова наш
        $turn = $this->request($alice, 'POST', "/api/games/{$started['game']['id']}/actions", ['type' => 'end_turn']);
        self::assertResponseIsSuccessful();
        self::assertSame($you, $turn['state']['activePlayer']);
        $turnStarts = array_column(array_filter($turn['events'], static fn (array $e) => $e['type'] === 'TurnStarted'), 'player');
        self::assertSame([$computer, $you], $turnStarts);

        // По шагам: наш «конец хода», затем каждое действие компьютера — с полем после него
        self::assertGreaterThanOrEqual(2, count($turn['steps']));
        self::assertSame('TurnEnded', $turn['steps'][0]['events'][0]['type']);
        self::assertSame($computer, $turn['steps'][0]['state']['activePlayer'], 'после нашего шага ход у компьютера');
        self::assertSame($turn['state'], end($turn['steps'])['state'], 'последний шаг — итоговое поле');
        self::assertSame($turn['events'], array_merge(...array_column($turn['steps'], 'events')));

        $updates = static::getContainer()->get(RecordingHub::class)->updates;
        self::assertCount(1, $updates, 'событие — только человеку');
    }

    public function testSurrender(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);

        $result = $this->request($bob, 'POST', "/api/games/{$gameId}/actions", ['type' => 'surrender']);

        self::assertResponseIsSuccessful();
        self::assertSame('finished', $result['game']['status']);
        self::assertSame($result['state']['you'] === 0 ? 1 : 0, $result['game']['winner'], 'победа — у соперника');
        self::assertSame(['PlayerSurrendered', 'GameWon'], array_column($result['events'], 'type'));
    }

    public function testTurnPassesWhenTimeIsUp(): void
    {
        $clock = self::mockTime('2026-10-06 12:00:00');
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);

        $view = $this->request($alice, 'GET', "/api/games/{$gameId}");
        $first = $view['state']['activePlayer'];
        self::assertSame('2026-10-06T12:01:30+00:00', $view['game']['turnDeadline'], '90 секунд на ход');

        $clock->sleep(89);
        self::assertSame($first, $this->request($alice, 'GET', "/api/games/{$gameId}")['state']['activePlayer'], 'время ещё есть');

        $clock->sleep(2);
        $view = $this->request($alice, 'GET', "/api/games/{$gameId}");
        self::assertSame(1 - $first, $view['state']['activePlayer'], 'время вышло — ход у соперника');
        self::assertSame(['TurnEnded', 'TurnStarted', 'CardDrawn'], array_column($view['events'], 'type'));
        self::assertSame('2026-10-06T12:03:01+00:00', $view['game']['turnDeadline'], 'у соперника снова 90 секунд');
        self::assertCount(2, static::getContainer()->get(RecordingHub::class)->updates, 'оба участника узнали');
    }

    public function testLateActionIsRejectedAfterTurnPassed(): void
    {
        $clock = self::mockTime('2026-10-06 12:00:00');
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);
        $view = $this->request($alice, 'GET', "/api/games/{$gameId}");
        $late = $view['game']['players'][$view['state']['activePlayer']]['id'] === $alice->getId() ? $alice : $bob;

        $clock->sleep(120);
        $this->request($late, 'POST', "/api/games/{$gameId}/actions", ['type' => 'end_turn']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Сейчас ход другого игрока.', $this->response()['error']);
        self::assertCount(2, static::getContainer()->get(RecordingHub::class)->updates, 'переход хода всё равно разослан');
    }

    public function testHistoryRestoresLogAfterReload(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $gameId = $this->startedGame($alice, $bob);
        $view = $this->request($alice, 'GET', "/api/games/{$gameId}");
        $first = $view['game']['players'][$view['state']['activePlayer']]['id'] === $alice->getId() ? $alice : $bob;
        $this->request($first, 'POST', "/api/games/{$gameId}/actions", ['type' => 'end_turn']);

        $aliceView = $this->request($alice, 'GET', "/api/games/{$gameId}");

        // Старт партии и конец хода — два хода, по порядку
        self::assertCount(2, $aliceView['history']);
        self::assertNull($aliceView['history'][0]['actor'], 'старт — ничей ход');
        self::assertSame('TurnEnded', $aliceView['history'][1]['events'][0]['type']);

        // Что взяла Алиса — видно ей, что взял Боб — нет
        $you = $aliceView['state']['you'];
        foreach (array_merge(...array_column($aliceView['history'], 'events')) as $event) {
            if ($event['type'] === 'CardDrawn') {
                $event['player'] === $you ? self::assertIsInt($event['card']) : self::assertNull($event['card']);
            }
        }
    }

        private function startedGame(User $creator, User $joiner): int
    {
        $created = $this->request($creator, 'POST', '/api/games', ['deckId' => $this->baseDeckId()]);
        $this->request($joiner, 'POST', "/api/games/{$created['game']['id']}/join", ['deckId' => $this->baseDeckId()]);
        self::assertResponseIsSuccessful();

        return $created['game']['id'];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(User $user, string $method, string $uri, ?array $body = null): array
    {
        $this->client->request($method, $uri, server: $this->headers($user), content: $body !== null ? json_encode($body) : null);

        return $this->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function response(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function headers(User $user): array
    {
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json'];
    }

    private function user(string $name): User
    {
        $user = (new User())
            ->setEmail(sprintf('%s.%s@test.duel', $name, bin2hex(random_bytes(4))))
            ->setNickname($name)
            ->setRoles(['ROLE_PLAYER'])
            ->setPassword('не используется — вход по JWT');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->users[] = $user;
    }

    private function baseDeckId(): int
    {
        return $this->entityManager->getRepository(Deck::class)->findOneBy(['owner' => null], ['id' => 'ASC'])->getId();
    }
}
