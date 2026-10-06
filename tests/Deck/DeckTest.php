<?php

namespace App\Tests\Deck;

use App\Entity\Card;
use App\Entity\CardType;
use App\Entity\Deck;
use App\Entity\Rarity;
use App\Entity\User;
use App\Game\Factory\CardDefinitionFactory;
use App\Game\GameRules;
use App\Game\Model\CardDefinition;
use App\Game\Model\GameState;
use App\Model\CreateDeckModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Правила колоды (CreateDeckModel) и колода → партия. Без базы: сущности собираются в памяти.
 */
final class DeckTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testValidDeck(): void
    {
        self::assertSame([], $this->errors($this->model($this->validCards())));
    }

    public function testDeckMustHaveExactSize(): void
    {
        $cards = $this->validCards();
        array_pop($cards);

        self::assertSame(['В колоде 18 карт, нужно ровно 20.'], $this->errors($this->model($cards)));
    }

    public function testCopiesLimit(): void
    {
        $cards = [['card' => $this->card('A'), 'quantity' => 4], ...array_slice($this->validCards(), 1)];
        $cards[] = ['card' => $this->card('Z'), 'quantity' => 1];

        self::assertContains('A «A»: копий 4, можно от 1 до 3.', $this->errors($this->model($cards)));
    }

    public function testLegendaryOnlyOnce(): void
    {
        $cards = array_slice($this->validCards(), 1);
        $cards[] = ['card' => $this->card('Дракон', rarity: 'legendary'), 'quantity' => 3];

        self::assertSame(['Дракон «Дракон»: копий 3, можно от 1 до 1.'], $this->errors($this->model($cards)));
    }

    public function testBuildingIsNotDeckCard(): void
    {
        $cards = array_slice($this->validCards(), 1);
        $cards[] = ['card' => $this->card('Кузница', type: 'building'), 'quantity' => 3];

        self::assertSame(['Кузница «Кузница» — постройка, в колоду не входит.'], $this->errors($this->model($cards)));
    }

    public function testSlugRequiredOnlyForBaseDeck(): void
    {
        self::assertSame(['Укажите slug базовой колоды.'], $this->errors(new CreateDeckModel('Без slug', $this->validCards())));
        self::assertSame([], $this->errors(new CreateDeckModel('Моя колода', $this->validCards(), owner: new User())));
    }

    public function testReplaceCardsKeepsExistingRows(): void
    {
        [$a, $b, $c] = [$this->card('A'), $this->card('B'), $this->card('C')];
        $deck = (new Deck())->replaceCards([['card' => $a, 'quantity' => 3], ['card' => $b, 'quantity' => 2]]);
        $rowA = $deck->getCards()->first();

        $deck->replaceCards([['card' => $a, 'quantity' => 1], ['card' => $c, 'quantity' => 3]]);

        $quantities = [];
        foreach ($deck->getCards() as $deckCard) {
            $quantities[$deckCard->getCard()->getName()] = $deckCard->getQuantity();
        }
        self::assertSame(['A' => 1, 'C' => 3], $quantities, 'B убрана, C добавлена');
        self::assertContains($rowA, $deck->getCards()->toArray(), 'строка A та же — поменялось только число');
    }

    public function testDeckBecomesGameDeck(): void
    {
        $deck = (new Deck())->replaceCards($this->validCards());

        $definitions = (new CardDefinitionFactory())->fromDeck($deck);
        $state = GameState::create($definitions, $definitions, $this->buildings(), seed: 1);

        self::assertCount(GameRules::DECK_SIZE, $definitions);
        self::assertCount(GameRules::DECK_SIZE, $state->player(0)->deck);
    }

    /**
     * @return list<CardDefinition>
     */
    private function buildings(): array
    {
        $factory = new CardDefinitionFactory();

        return [$factory->fromCard($this->card('Кузница', type: 'building')), $factory->fromCard($this->card('Башня', type: 'building'))];
    }

    /**
     * @return list<array{card: Card, quantity: int}> 20 карт: 6 карт по 3 + 1 по 2
     */
    private function validCards(): array
    {
        $cards = [];
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $name) {
            $cards[] = ['card' => $this->card($name), 'quantity' => 3];
        }
        $cards[] = ['card' => $this->card('G'), 'quantity' => 2];

        return $cards;
    }

    /**
     * @param list<array{card: Card, quantity: int}> $cards
     */
    private function model(array $cards): CreateDeckModel
    {
        return new CreateDeckModel('Колода', $cards, slug: 'deck');
    }

    /**
     * @return list<string>
     */
    private function errors(CreateDeckModel $model): array
    {
        $messages = [];
        foreach ($this->validator->validate($model) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    private function card(string $name, string $type = 'creature', string $rarity = 'common'): Card
    {
        return (new Card())
            ->setName($name)
            ->setVendorCode($name)
            ->setCardType((new CardType())->setSlug($type))
            ->setRarity((new Rarity())->setSlug($rarity));
    }
}
