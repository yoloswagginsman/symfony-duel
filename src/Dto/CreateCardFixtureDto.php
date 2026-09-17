<?php

namespace App\Dto;
;
use App\Contract\Dto\ToModelConvertibleInterface;
use App\Entity\Ability;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use App\Model\CreateCardModel;

readonly class CreateCardFixtureDto implements ToModelConvertibleInterface
{
    /**
     * @param array<Tag> $tags
     * @param array<array{ability: Ability, value: ?int}> $abilities
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public int $manaCost,
        public ?int $attack,
        public ?int $health,
        public ?string $imagePath,
        public ?Race $race,
        public ?CardType $cardType,
        public ?Rarity $rarity,
        public array $tags = [],
        public array $abilities = [],
    ) {
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
            isActive: false,
            race: $this->race,
            cardType: $this->cardType,
            rarity: $this->rarity,
            tags: $this->tags,
            abilities: $this->abilities
        );
    }
}