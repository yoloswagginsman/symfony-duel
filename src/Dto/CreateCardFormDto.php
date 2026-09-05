<?php

namespace App\Dto;

use App\Entity\CardTypes;
use App\Entity\Races;
use App\Entity\Rarities;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class CreateCardFormDto
{
    #[Assert\NotBlank(message: 'Укажите название карты')]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?int $id = null;
    public ?string $imagePath = null;

    public ?string $description = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Assert\Range(
        notInRangeMessage: 'Стоимость маны должна быть от {{ min }} до {{ max }}.',
        min: 0,
        max: 30
    )]
    public int $manaCost = 0;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Assert\Range(
        notInRangeMessage: 'Атака должна быть от {{ min }} до {{ max }}.',
        min: 0,
        max: 30
    )]
    public ?int $attack = null;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Assert\Range(
        notInRangeMessage: 'Здоровье должно быть от {{ min }} до {{ max }}.',
        min: 1,
        max: 30
    )]
    public ?int $health = 1;

    #[Assert\NotNull(message: 'Выберите тип карты.')]
    public ?CardTypes $cardType = null;

    #[Assert\NotNull(message: 'Выберите расу.')]
    public ?Races $race = null;

    #[Assert\NotNull(message: 'Выберите редкость.')]
    public ?Rarities $rarity = null;

    /** @var Collection<int, mixed> */
    public Collection $tags;

    public ?UploadedFile $imageFile = null;
    public bool $isActive = true;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }
}