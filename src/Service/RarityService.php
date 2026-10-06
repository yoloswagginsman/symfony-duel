<?php

namespace App\Service;

use App\Entity\Rarity;
use App\Model\CreateRarityModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class RarityService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateRarityModel $model): Rarity
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $rarity = (new Rarity())
                ->setSlug($model->slug);
            $this->applyModelToEntity($rarity, $model);

            $this->entityManager->persist($rarity);

            return $rarity;
        });
    }

    /**
     * slug не меняется — по нему запись находят карты и импорт контента.
     */
    public function update(Rarity $rarity, CreateRarityModel $model): Rarity
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($rarity, $model) {
            $this->applyModelToEntity($rarity, $model);

            return $rarity;
        });
    }

    private function validateModel(CreateRarityModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Rarity $rarity, CreateRarityModel $model): void
    {
        $rarity
            ->setName($model->name)
            ->setColorHex($model->colorHex)
            ->setDropChance($model->dropChance);
    }
}
