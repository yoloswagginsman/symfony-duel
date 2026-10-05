<?php

namespace App\Service;

use App\Entity\Race;
use App\Model\CreateRaceModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class RaceService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateRaceModel $model): Race
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $race = (new Race())
                ->setSlug($model->slug);
            $this->applyModelToEntity($race, $model);

            $this->entityManager->persist($race);

            return $race;
        });
    }

    /**
     * slug не меняется — по нему запись находят карты и импорт контента.
     */
    public function update(Race $race, CreateRaceModel $model): Race
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($race, $model) {
            $this->applyModelToEntity($race, $model);

            return $race;
        });
    }

    private function validateModel(CreateRaceModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Race $race, CreateRaceModel $model): void
    {
        $race
            ->setName($model->name)
            ->setDescription($model->description);
    }
}
