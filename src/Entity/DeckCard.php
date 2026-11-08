<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Карта в колоде и сколько её копий.
 */
#[ORM\Entity]
#[ORM\Table(name: 'deck_cards')]
#[ORM\UniqueConstraint(name: 'uniq_deck_cards_deck_card', columns: ['deck_id', 'card_id'])]
class DeckCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'cards')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Deck $deck,

        // Карту из колоды удалить нельзя (RESTRICT) — сначала убрать её из колод
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Card $card,

        #[ORM\Column(type: Types::SMALLINT)]
        private int $quantity,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function getCard(): Card
    {
        return $this->card;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
