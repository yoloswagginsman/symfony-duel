<?php

namespace App\Factory;

use App\Dto\CreateCardFormDto;
use App\Entity\Cards;
use App\Service\ImageUploader;

readonly class CardFactory
{
    public function __construct(
        private ImageUploader $imageUploader
    ) {}

    public function createFromFormDto(CreateCardFormDto $dto): Cards
    {
        $card = new Cards();
        $card->setName($dto->name);
        $card->setDescription($dto->description);
        $card->setManaCost($dto->manaCost);
        $card->setAttack($dto->attack);
        $card->setHealth($dto->health);
        $card->setRace($dto->race);
        $card->setCardType($dto->cardType);
        $card->setRarity($dto->rarity);
        $card->setIsActive($dto->isActive);
        $card->setImagePath($dto->imagePath);

        // Привязываем теги из коллекции DTO
        foreach ($dto->tags as $tag) {
            $card->addTag($tag);
        }

        // Загрузка изображения, если файл передан
        if ($dto->imageFile !== null) {
            $newPath = $this->imageUploader->upload($card, $dto->imageFile);
            $card->setImagePath($newPath);
        }

        return $card;
    }
}