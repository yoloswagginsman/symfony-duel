<?php

namespace App\Dto;

use App\Contract\Dto\ToModelConvertibleInterface;
use App\Entity\Ability;
use App\Entity\Card;
use App\Entity\CardAbility;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use App\Model\CreateCardModel;

/**
 * Карта из контента (data/content/cards/<тип>/<раса>.yaml). Справочники уже найдены по slug (это делает CardYamlDtoFactory) —
 * как в CreateCardFormDto, где их находит форма. Проверка полей — у модели (CardService).
 *
 * @implements ToModelConvertibleInterface<CreateCardModel>
 */
final readonly class CardYamlDto implements ToModelConvertibleInterface
{
    /**
     * @param list<Tag>                                   $tags
     * @param list<array{ability: Ability, value: ?int}>  $abilities value — null, если у способности нет числа
     */
    public function __construct(
        public ?string $name = null,
        public ?int $manaCost = null,
        public ?Race $race = null,
        public ?CardType $cardType = null,
        public ?Rarity $rarity = null,
        public ?string $vendorCode = null,
        public ?string $description = null,
        public ?string $imagePath = null,
        public ?int $attack = null,
        public ?int $health = null,
        public bool $isActive = false,
        public array $tags = [],
        public array $abilities = [],
    ) {
    }

    public static function fromCard(Card $card): self
    {
        return new self(
            name: $card->getName(),
            manaCost: $card->getManaCost(),
            race: $card->getRace(),
            cardType: $card->getCardType(),
            rarity: $card->getRarity(),
            vendorCode: $card->getVendorCode(),
            description: $card->getDescription() ?: null,
            imagePath: $card->getImagePath(),
            attack: $card->getAttack(),
            health: $card->getHealth(),
            isActive: (bool) $card->isActive(),
            tags: $card->getTags()->getValues(),
            abilities: array_map(
                static fn (CardAbility $cardAbility) => ['ability' => $cardAbility->getAbility(), 'value' => $cardAbility->getValue()],
                $card->getAbilities()->getValues(),
            ),
        );
    }

    public function toModel(): CreateCardModel
    {
        return new CreateCardModel(
            name: $this->name,
            description: $this->description,
            manaCost: $this->manaCost,
            attack: $this->attack,
            health: $this->health,
            imagePath: $this->imagePath,
            isActive: $this->isActive,
            race: $this->race,
            cardType: $this->cardType,
            rarity: $this->rarity,
            tags: $this->tags,
            abilities: $this->abilities,
            vendorCode: $this->vendorCode,
        );
    }

    /**
     * Запись для файла карт: справочники — по slug, способности — по имени,
     * поля без значения не пишутся, способность без числа — просто имя.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'vendorCode' => $this->vendorCode,
            'name' => $this->name,
            'description' => $this->description,
            'imagePath' => $this->imagePath,
            'manaCost' => $this->manaCost,
            'attack' => $this->attack,
            'health' => $this->health,
            'race' => $this->race?->getSlug(),
            'type' => $this->cardType?->getSlug(),
            'rarity' => $this->rarity?->getSlug(),
            'isActive' => $this->isActive,
            'tags' => array_map(static fn (Tag $tag) => $tag->getSlug(), $this->tags),
            'abilities' => array_map(
                static fn (array $a) => $a['value'] === null
                    ? $a['ability']->getName()
                    : ['name' => $a['ability']->getName(), 'value' => $a['value']],
                $this->abilities,
            ),
        ], static fn (mixed $value) => $value !== null);
    }
}
