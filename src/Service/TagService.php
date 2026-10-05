<?php

namespace App\Service;

use App\Entity\Tag;
use App\Model\CreateTagModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class TagService
{
    public function __construct(
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateTagModel $model): Tag
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($model) {
            $tag = (new Tag())
                ->setSlug($model->slug)
                ->setIsActive(true)
                ->setCreatedAt(new \DateTime());
            $this->applyModelToEntity($tag, $model);

            $this->entityManager->persist($tag);

            return $tag;
        });
    }

    /**
     * slug не меняется — по нему запись находят карты и импорт контента.
     */
    public function update(Tag $tag, CreateTagModel $model): Tag
    {
        $this->validateModel($model);

        return $this->entityManager->wrapInTransaction(function () use ($tag, $model) {
            $this->applyModelToEntity($tag, $model);

            return $tag;
        });
    }

    private function validateModel(CreateTagModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Tag $tag, CreateTagModel $model): void
    {
        $tag
            ->setName($model->name)
            ->setCategory($model->category)
            ->setIcon($model->icon)
            ->setColorHex($model->colorHex);
    }
}
