<?php

namespace App\Controller\Web\Card;

use App\Controller\Web\Card\Output\CardFormResult;
use App\Dto\CreateCardFormDto;
use App\Entity\Card;
use App\Form\CardsType;
use App\Service\CardService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class Manager
{
    public function __construct(
        private CardService $cardService,
        private FormFactoryInterface $formFactory
    ) {
    }

    public function getFormData(Request $request, ?Card $card = null): CardFormResult
    {
        $isNew = $card === null;
        $isSubmittedAndValid = false;

        $formData = $isNew ? new CreateCardFormDto() : new CreateCardFormDto(
            name: $card->getName(),
            id: $card->getId(),
            imagePath: $card->getImagePath(),
            description: $card->getDescription(),
            manaCost: $card->getManaCost(),
            attack: $card->getAttack(),
            health: $card->getHealth(),
            cardType: $card->getCardType(),
            race: $card->getRace(),
            rarity: $card->getRarity(),
            tags: $card->getTags(),
            abilitiesForm: $card->getAbilities(),
            imageFile: null, // Изначально при открытии формы файл пуст
            isActive: $card->isActive() ?? true,
        );

        $form = $this->formFactory->create(CardsType::class, $formData, ['isNew' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateCardFormDto $cardFormDto */
            $cardFormDto = $form->getData();

            // $cardFormDto->imageFile уже содержит UploadedFile благодаря Symfony Form
            $cardModel = $cardFormDto->toModel();

            // ⚡ Разделяем создание и обновление
            if ($isNew) {
                $card = $this->cardService->create($cardModel);
            } else {
                $card = $this->cardService->update($card, $cardModel);
            }

            $isSubmittedAndValid = true;
        }

        return new CardFormResult(
            form: $form,
            isNew: $isNew,
            card: $card ?? new Card(),
            isSubmittedAndValid: $isSubmittedAndValid,
        );
    }
}