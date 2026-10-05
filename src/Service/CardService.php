<?php

namespace App\Service;

use App\Entity\Ability;
use App\Entity\Card;
use App\Model\CreateCardModel;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class CardService
{
    public function __construct(
        private CardRepository $cardRepository,
        private ImageUploader $imageUploader,
        private ValidatorInterface $validator,
        private EntityManagerInterface $em,
    ) {
    }

    public function create(CreateCardModel $model): Card
    {
        $this->validateModel($model);

        return $this->em->wrapInTransaction(function () use ($model) {
            $card = new Card();
            $this->applyModelToEntity($card, $model);

            $this->cardRepository->create($card);

            return $card;
        });
    }

    public function update(Card $card, CreateCardModel $model): Card
    {
        $this->validateModel($model);

        return $this->em->wrapInTransaction(function () use ($card, $model) {

            // ⚡ Очищаем старые связи для корректной синхронизации при обновлении
            $card->clearAbilities();
            $this->applyModelToEntity($card, $model);

            $this->cardRepository->create($card);

            return $card;
        });
    }

    private function validateModel(CreateCardModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }

    private function applyModelToEntity(Card $card, CreateCardModel $model): void
    {
        $card->setName($model->name);
        $card->setDescription($model->description);
        $card->setManaCost($model->manaCost);
        $card->setAttack($model->attack);
        $card->setHealth($model->health);
        $card->setIsActive($model->isActive);

        $card->setRace($model->race);
        $card->setCardType($model->cardType);
        $card->setRarity($model->rarity);

        // Устанавливаем базовый путь из модели (например, из фикстур), если он задан
        if ($model->imagePath !== null) {
            $card->setImagePath($model->imagePath);
        }

        // Привязываем теги
        $card->setTags($model->tags);

        // Привязываем способности
        foreach ($model->abilities as $item) {
            if ($item instanceof Ability) {
                $card->addAbility($item);
            } elseif (is_array($item) && isset($item['ability']) && $item['ability'] instanceof Ability) {
                $card->addAbilityWithValue($item['ability'], $item['value'] ?? null);
            }
        }

        // Загрузка нового файла (если передан через форму)
        if ($model->imageFile !== null) {
            // Если у карты уже была картинка — удаляем старый файл
            if ($card->getImagePath() !== null) {
                $this->imageUploader->remove($card->getImagePath());
            }

            $newPath = $this->imageUploader->upload($card, $model->imageFile);
            $card->setImagePath($newPath);
        }
    }
}