<?php

namespace App\Service;

use App\Entity\Deck;
use App\Model\CreateDeckModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class DeckService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateDeckModel $model): Deck
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $deck = (new Deck())
                ->setSlug($model->slug)
                ->setOwner($model->owner);
            $this->applyModelToEntity($deck, $model);

            $this->entityManager->persist($deck);

            return $deck;
        });
    }

    /**
     * slug и владелец не меняются: по slug базовую колоду находит импорт контента.
     */
    public function update(Deck $deck, CreateDeckModel $model): Deck
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($deck, $model) {
            $this->applyModelToEntity($deck, $model);

            return $deck;
        });
    }

    public function delete(Deck $deck): void
    {
        $this->entityManager->remove($deck);
        $this->entityManager->flush();
    }

    private function validateModel(CreateDeckModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Deck $deck, CreateDeckModel $model): void
    {
        $deck
            ->setName($model->name)
            ->setDescription($model->description)
            ->replaceCards($model->cards);
    }
}
