<?php

namespace App\Tests\Controller;

use App\Entity\Cards;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CardsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<Cards> */
    private EntityRepository $cardRepository;
    private string $path = '/cards/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->cardRepository = $this->manager->getRepository(Cards::class);

        foreach ($this->cardRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Card index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'card[name]' => 'Testing',
            'card[description]' => 'Testing',
            'card[manaCost]' => 'Testing',
            'card[attack]' => 'Testing',
            'card[health]' => 'Testing',
            'card[imagePath]' => 'Testing',
            'card[isActive]' => 'Testing',
            'card[createdAt]' => 'Testing',
            'card[updatedAt]' => 'Testing',
            'card[tags]' => 'Testing',
            'card[race]' => 'Testing',
            'card[cardType]' => 'Testing',
            'card[rarity]' => 'Testing',
        ]);

        self::assertResponseRedirects('/cards');

        self::assertSame(1, $this->cardRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = new Cards();
        $fixture->setName('My Title');
        $fixture->setDescription('My Title');
        $fixture->setManaCost('My Title');
        $fixture->setAttack('My Title');
        $fixture->setHealth('My Title');
        $fixture->setImagePath('My Title');
        $fixture->setIsActive('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setTags('My Title');
        $fixture->setRace('My Title');
        $fixture->setCardType('My Title');
        $fixture->setRarity('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Card');

        // Use assertions to check that the properties are properly displayed.
        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = new Cards();
        $fixture->setName('Value');
        $fixture->setDescription('Value');
        $fixture->setManaCost('Value');
        $fixture->setAttack('Value');
        $fixture->setHealth('Value');
        $fixture->setImagePath('Value');
        $fixture->setIsActive('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');
        $fixture->setTags('Value');
        $fixture->setRace('Value');
        $fixture->setCardType('Value');
        $fixture->setRarity('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'card[name]' => 'Something New',
            'card[description]' => 'Something New',
            'card[manaCost]' => 'Something New',
            'card[attack]' => 'Something New',
            'card[health]' => 'Something New',
            'card[imagePath]' => 'Something New',
            'card[isActive]' => 'Something New',
            'card[createdAt]' => 'Something New',
            'card[updatedAt]' => 'Something New',
            'card[tags]' => 'Something New',
            'card[race]' => 'Something New',
            'card[cardType]' => 'Something New',
            'card[rarity]' => 'Something New',
        ]);

        self::assertResponseRedirects('/cards');

        $fixture = $this->cardRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getDescription());
        self::assertSame('Something New', $fixture[0]->getManaCost());
        self::assertSame('Something New', $fixture[0]->getAttack());
        self::assertSame('Something New', $fixture[0]->getHealth());
        self::assertSame('Something New', $fixture[0]->getImagePath());
        self::assertSame('Something New', $fixture[0]->getIsActive());
        self::assertSame('Something New', $fixture[0]->getCreatedAt());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());
        self::assertSame('Something New', $fixture[0]->getTags());
        self::assertSame('Something New', $fixture[0]->getRace());
        self::assertSame('Something New', $fixture[0]->getCardType());
        self::assertSame('Something New', $fixture[0]->getRarity());

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = new Cards();
        $fixture->setName('Value');
        $fixture->setDescription('Value');
        $fixture->setManaCost('Value');
        $fixture->setAttack('Value');
        $fixture->setHealth('Value');
        $fixture->setImagePath('Value');
        $fixture->setIsActive('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');
        $fixture->setTags('Value');
        $fixture->setRace('Value');
        $fixture->setCardType('Value');
        $fixture->setRarity('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/cards');
        self::assertSame(0, $this->cardRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}
