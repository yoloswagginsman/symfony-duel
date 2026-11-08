<?php

namespace App\Dto;

use App\Contract\Dto\ToModelConvertibleInterface;
use App\Entity\Card;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Model\CreateDeckModel;

/**
 * Базовая колода из data/content/decks.yaml. Карты уже найдены по vendorCode (DeckYamlDtoFactory).
 * Проверка состава — у модели (DeckService).
 *
 * @implements ToModelConvertibleInterface<CreateDeckModel>
 */
final readonly class DeckYamlDto implements ToModelConvertibleInterface
{
    /**
     * @param list<array{card: Card, quantity: int}> $cards
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description = null,
        public array $cards = [],
    ) {
    }

    public static function fromDeck(Deck $deck): self
    {
        return new self(
            slug: (string) $deck->getSlug(),
            name: (string) $deck->getName(),
            description: $deck->getDescription(),
            cards: array_map(
                static fn (DeckCard $deckCard) => ['card' => $deckCard->getCard(), 'quantity' => $deckCard->getQuantity()],
                $deck->getCards()->getValues(),
            ),
        );
    }

    public function toModel(): CreateDeckModel
    {
        return new CreateDeckModel(
            name: $this->name,
            cards: $this->cards,
            slug: $this->slug,
            description: $this->description,
        );
    }

    /**
     * Для сравнения «было → стало»: карты — «артикул ×копий».
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'cards' => array_map(
                static fn (array $item) => sprintf('%s ×%d', $item['card']->getVendorCode(), $item['quantity']),
                $this->cards,
            ),
        ];
    }
}
