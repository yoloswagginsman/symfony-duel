<?php

namespace App\Service;

use App\Entity\CardType;
use App\Model\CreateCardTypeModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class CardTypeService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateCardTypeModel $model): CardType
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $type = (new CardType())
                ->setSlug($model->slug);
            $this->applyModelToEntity($type, $model);

            $this->entityManager->persist($type);

            return $type;
        });
    }

    /**
     * slug не меняется — по нему запись находят карты и импорт контента.
     */
    public function update(CardType $type, CreateCardTypeModel $model): CardType
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($type, $model) {
            $this->applyModelToEntity($type, $model);

            return $type;
        });
    }

    private function validateModel(CreateCardTypeModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(CardType $type, CreateCardTypeModel $model): void
    {
        $type->setName($model->name);
    }
}
