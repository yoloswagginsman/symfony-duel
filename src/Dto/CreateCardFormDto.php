<?php

namespace App\Dto;

use App\Contract\Dto\ToModelConvertibleInterface;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Model\CreateCardModel;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CreateCardFormDto implements ToModelConvertibleInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название карты')]
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        public ?int $id = null,

        public ?string $imagePath = null,

        public ?string $description = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        #[Assert\Range(
            notInRangeMessage: 'Стоимость маны должна быть от {{ min }} до {{ max }}.',
            min: 0,
            max: 30
        )]
        public ?int $manaCost = 0,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        #[Assert\Range(
            notInRangeMessage: 'Атака должна быть от {{ min }} до {{ max }}.',
            min: 0,
            max: 30
        )]
        public ?int $attack = null,

        #[Assert\PositiveOrZero]
        #[Assert\Range(
            notInRangeMessage: 'Здоровье должно быть от {{ min }} до {{ max }}.',
            min: 1,
            max: 30
        )]
        public ?int $health = 1,

        #[Assert\NotNull(message: 'Выберите тип карты.')]
        public ?CardType $cardType = null,

        #[Assert\NotNull(message: 'Выберите расу.')]
        public ?Race $race = null,

        #[Assert\NotNull(message: 'Выберите редкость.')]
        public ?Rarity $rarity = null,

        public Collection|array $tags = [],

        #[Assert\Valid]
        /** @var CardAbilityFormDto[]|Collection */
        public Collection|array $abilitiesForm = [],

        #[Assert\Image(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            detectCorrupted: true,
            maxSizeMessage: 'Размер файла не должен превышать 5 МБ',
            mimeTypesMessage: 'Загрузите изображение формата JPEG, PNG или WEBP'
        )]
        public ?UploadedFile $imageFile = null,

        public bool $isActive = true,
    ) {
    }

    public function toModel(): CreateCardModel
    {
        $abilities = [];

        foreach ($this->abilitiesForm as $item) {
            if ($item instanceof CardAbilityFormDto && $item->enabled && $item->ability !== null) {
                $abilities[] = [
                    'ability' => $item->ability,
                    'value'   => (int) $item->value,
                ];
            }
        }

        return new CreateCardModel(
            name: $this->name,
            description: $this->description,
            manaCost: $this->manaCost,
            attack: $this->attack,
            health: $this->health,
            imagePath: $this->imagePath,
            imageFile: $this->imageFile,
            isActive: $this->isActive,
            race: $this->race,
            cardType: $this->cardType,
            rarity: $this->rarity,
            tags: $this->tags,
            abilities: $abilities,
        );
    }
}