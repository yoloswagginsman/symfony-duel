<?php

namespace App\Model;

use App\Entity\Ability;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateCardModel
{
    /**
     * @param Collection<int, Tag>|array<int, Tag> $tags
     * @param Collection<int, Ability|array{ability: Ability, value: ?int}>|array<int, Ability|array{ability: Ability, value: ?int}> $abilities
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Название карты обязательно.')]
        public string $name,

        public ?string $description = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $manaCost = 0,

        #[Assert\PositiveOrZero]
        public ?int $attack = null,

        #[Assert\PositiveOrZero]
        public ?int $health = null,

        public ?string $imagePath = null,

        public ?UploadedFile $imageFile = null,

        public bool $isActive = true,

        #[Assert\NotNull(message: 'Укажите расу.')]
        public ?Race $race = null,

        #[Assert\NotNull(message: 'Укажите тип карты.')]
        public ?CardType $cardType = null,

        #[Assert\NotNull(message: 'Укажите редкость.')]
        public ?Rarity $rarity = null,

        public Collection|array $tags = [],

        public Collection|array $abilities = [],
    ) {
    }
}