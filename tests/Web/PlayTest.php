<?php

namespace App\Tests\Web;

use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\User;
use App\Tests\Support\RecordingHub;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Лобби на сайте (сессия + CSRF из формы) и выдача JWT странице партии. База — cards_test с контентом.
 */
final class PlayTest extends WebTestCase
{
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
        foreach ($this->users as $user) {
            $this->entityManager->remove($this->entityManager->find(User::class, $user->getId()));
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testLobbyShowsDecksAndStartsComputerGame(): void
    {
        $this->client->loginUser($this->user('alice'));

        $crawler = $this->client->request('GET', '/play');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.deck-list', 'Стражи леса');

        $this->client->submit($crawler->selectButton('⚔️ Играть с компьютером')->form());
        self::assertResponseRedirects();
        self::assertMatchesRegularExpression('#^/play/\d+$#', $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.board-bar-title', '№');
        self::assertSelectorNotExists('nav.main-nav', 'в режиме игры меню сайта нет');
    }

    public function testCreatedGameIsJoinedFromAnotherLobby(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];

        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/play');
        $this->client->submit($crawler->selectButton('✦ Создать партию для игроков')->form());
        $gameUrl = $this->client->getResponse()->headers->get('Location');

        $this->client->loginUser($bob);
        $crawler = $this->client->request('GET', '/play');
        self::assertSelectorTextContains('.game-list', 'alice');
        $this->client->submit($crawler->selectButton('Принять вызов выбранной колодой')->form());

        self::assertResponseRedirects($gameUrl);
        self::assertCount(2, static::getContainer()->get(RecordingHub::class)->updates, 'создатель, ждущий на странице партии, узнаёт о старте');
        $this->client->request('GET', '/play');
        self::assertSelectorTextContains('.lobby-section:last-child .game-list', 'против alice');
    }

    public function testErrorsReturnToLobbyWithMessage(): void
    {
        $alice = $this->user('alice');
        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/play');
        $this->client->submit($crawler->selectButton('✦ Создать партию для игроков')->form());

        // Своя партия в «Ждут соперника» не показывается — присоединиться к ней напрямую
        $gameId = (int) basename($this->client->getResponse()->headers->get('Location'));
        $form = $crawler->selectButton('⚔️ Играть с компьютером')->form();
        $this->client->request('POST', "/play/{$gameId}/join", ['_token' => $form['_token']->getValue(), 'deck' => $form['deck']->getValue()]);

        self::assertResponseRedirects('/play');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-toast--error', 'Нельзя присоединиться к своей партии.');
    }

    public function testActionsRequireCsrfToken(): void
    {
        $alice = $this->user('alice');
        $this->client->loginUser($alice);

        $this->client->request('POST', '/play/computer', ['deck' => 1, '_token' => 'подделка']);

        // Неверный CSRF Symfony считает ошибкой входа — на страницу входа; партия не создана
        self::assertResponseRedirects('http://localhost/login');
        self::assertSame([], $this->entityManager->getRepository(Game::class)->findUnfinishedOf($alice));
    }

    public function testStrangerCannotOpenGame(): void
    {
        [$alice, $eve] = [$this->user('alice'), $this->user('eve')];
        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/play');
        $this->client->submit($crawler->selectButton('⚔️ Играть с компьютером')->form());
        $gameUrl = $this->client->getResponse()->headers->get('Location');

        $this->client->loginUser($eve);
        $this->client->request('GET', $gameUrl);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTokenForLoggedInPlayer(): void
    {
        $this->client->request('GET', '/play/token');
        self::assertResponseRedirects('/login', message: 'гостю — на вход');

        $alice = $this->user('alice');
        $this->client->loginUser($alice);
        $this->client->request('GET', '/play/token');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'), 'токен не кешируется');
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        // В токене — id и ник, без email (данные JWT читает любой, у кого он есть)
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        self::assertSame((string) $alice->getId(), $payload['sub']);
        self::assertSame('alice', $payload['nickname']);
        self::assertSame(['ROLE_PLAYER'], $payload['roles']);
        self::assertEqualsCanonicalizing(['iat', 'exp', 'sub', 'nickname', 'roles'], array_keys($payload));
        self::assertStringNotContainsString('@', json_encode($payload));
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertSame($alice->getId(), json_decode($this->client->getResponse()->getContent(), true)['id'], 'токен годится для API');
    }

    private function user(string $name): User
    {
        $user = (new User())
            ->setEmail(sprintf('%s.%s@test.duel', $name, bin2hex(random_bytes(4))))
            ->setNickname($name)
            ->setRoles(['ROLE_PLAYER'])
            ->setPassword('не используется — вход через loginUser');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->users[] = $user;
    }
}
