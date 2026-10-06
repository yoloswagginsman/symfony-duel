<?php

namespace App\Game\Factory;

use App\Entity\Card;
use App\Entity\CardAbility;
use App\Entity\Deck;
use App\Game\Enum\CardKind;
use App\Game\Model\AbilityRef;
use App\Game\Model\CardDefinition;

/**
 * Карта из базы (Card) → карта для движка партии (CardDefinition).
 * Единственное место, где движок соприкасается с Doctrine.
 */
final readonly class CardDefinitionFactory
{
    public function fromCard(Card $card): CardDefinition
    {
        return new CardDefinition(
            vendorCode: (string) $card->getVendorCode(),
            name: (string) $card->getName(),
            kind: CardKind::from((string) $card->getCardType()?->getSlug()),
            manaCost: (int) $card->getManaCost(),
            attack: $card->getAttack(),
            health: $card->getHealth(),
            race: $card->getRace()?->getSlug(),
            abilities: array_map(
                static fn (CardAbility $cardAbility) => new AbilityRef((string) $cardAbility->getAbility()?->getSlug(), $cardAbility->getValue()),
                $card->getAbilities()->getValues(),
            ),
        );
    }

    /**
     * Колода для GameState::create(): каждая карта — столько раз, сколько у неё копий.
     *
     * @return list<CardDefinition>
     */
    public function fromDeck(Deck $deck): array
    {
        $definitions = [];
        foreach ($deck->getCards() as $deckCard) {
            $definition = $this->fromCard($deckCard->getCard());
            for ($copy = 0; $copy < $deckCard->getQuantity(); ++$copy) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }
}
