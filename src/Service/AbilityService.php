<?php

namespace App\Service;

use App\Entity\Ability;
use App\Model\CreateAbilityModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class AbilityService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateAbilityModel $model): Ability
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $ability = (new Ability())
                ->setSlug($model->slug)
                ->setIsActive(true);
            $this->applyModelToEntity($ability, $model);

            $this->entityManager->persist($ability);

            return $ability;
        });
    }

    /**
     * slug не меняется — по нему запись находят карты и импорт контента.
     */
    public function update(Ability $ability, CreateAbilityModel $model): Ability
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($ability, $model) {
            $this->applyModelToEntity($ability, $model);

            return $ability;
        });
    }

    private function validateModel(CreateAbilityModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Ability $ability, CreateAbilityModel $model): void
    {
        $ability
            ->setName($model->name)
            ->setDescription($model->description);
    }
}
