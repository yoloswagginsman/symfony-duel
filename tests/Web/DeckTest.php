<?php

namespace App\Tests\Web;

use App\Entity\Card;
use App\Entity\Deck;
use App\Entity\User;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Раздел колод: список, конструктор (правила — на сервере), копия, удаление. База — cards_test с контентом.
 */
final class DeckTest extends WebTestCase
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
            self::markTestSkipped('В cards_test нет контента: APP_ENV=test php bin/console app:content:import');
        }
    }

    protected function tearDown(): void
    {
        // Колоды игроков удалятся каскадом
        foreach ($this->users as $user) {
            $this->entityManager->remove($this->entityManager->find(User::class, $user->getId()));
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testBuildValidDeck(): void
    {
        $this->client->loginUser($this->user('alice'));
        $crawler = $this->client->request('GET', '/decks/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.builder-card[data-type="building"]', 'постройки в колоду не входят');

        $form = $crawler->selectButton('Сохранить колоду')->form();
        $this->client->request('POST', '/decks/new', [
            '_token' => $form['_token']->getValue(),
            'name' => 'Моя первая',
            'cards' => $this->validCards(),
        ]);

        self::assertResponseRedirects('/decks');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-toast--success', 'Колода «Моя первая» сохранена.');
        self::assertSelectorTextContains('.deck-tile', 'Моя первая');
    }

    public function testRulesAreCheckedOnServer(): void
    {
        $this->client->loginUser($this->user('alice'));
        $crawler = $this->client->request('GET', '/decks/new');
        $token = $crawler->selectButton('Сохранить колоду')->form()['_token']->getValue();
        $cards = $this->validCards();
        array_pop($cards);
        $building = $this->entityManager->getRepository(Card::class)->findOneBy(['vendorCode' => 'DUEL-BUI-NEU-KUZNICA']);
        $cards[$building->getId()] = 2;   // постройку в колоду не положить — отбрасывается

        $this->client->request('POST', '/decks/new', ['_token' => $token, 'name' => 'Неполная', 'cards' => $cards]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.builder-errors', 'В колоде 18 карт, нужно ровно 20.');
        self::assertSelectorExists('input[name="name"][value="Неполная"]', 'введённое не теряется');
    }

    public function testCopyBaseDeckThenEditAndDelete(): void
    {
        $alice = $this->user('alice');
        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/decks');

        $this->client->submit($crawler->filter('.deck-tile')->reduce(
            static fn ($tile) => str_contains($tile->text(), 'Стражи леса'),
        )->selectButton('⧉ Копия в мои')->form());
        self::assertResponseRedirects();
        $editUrl = $this->client->getResponse()->headers->get('Location');

        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('input[name="name"][value="Стражи леса (копия)"]');
        $quantities = json_decode($crawler->filter('form.builder')->attr('data-deck-builder-quantities-value'), true);
        self::assertSame(20, array_sum($quantities), 'состав скопирован');

        $crawler = $this->client->request('GET', '/decks');
        $this->client->submit($crawler->selectButton('Удалить')->form());
        self::assertResponseRedirects('/decks');
        self::assertSame(0, $this->entityManager->getRepository(Deck::class)->count(['owner' => $alice]));
        self::assertNotNull($editUrl);
    }

    public function testCannotEditForeignOrBaseDeck(): void
    {
        [$alice, $bob] = [$this->user('alice'), $this->user('bob')];
        $base = $this->entityManager->getRepository(Deck::class)->findOneBy(['owner' => null]);

        $this->client->loginUser($bob);
        $this->client->request('GET', "/decks/{$base->getId()}/edit");
        self::assertResponseStatusCodeSame(404, 'базовые колоды меняются через контент');

        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', '/decks');
        $this->client->submit($crawler->selectButton('⧉ Копия в мои')->form());
        $editUrl = $this->client->getResponse()->headers->get('Location');

        $this->client->loginUser($bob);
        $this->client->request('GET', $editUrl);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * 20 карт по правилам: 6 обычных карт по 3 и одна по 2.
     *
     * @return array<int, int> id карты => копий
     */
    private function validCards(): array
    {
        /** @var CardRepository $repository */
        $repository = $this->entityManager->getRepository(Card::class);
        $ordinary = array_values(array_filter($repository->findDeckPool(), static fn (Card $card) => $card->getRarity()?->getSlug() !== 'legendary'));

        $cards = [];
        foreach (array_slice($ordinary, 0, 6) as $card) {
            $cards[$card->getId()] = 3;
        }
        $cards[$ordinary[6]->getId()] = 2;

        return $cards;
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
