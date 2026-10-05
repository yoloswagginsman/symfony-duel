<?php

namespace App\Form\EventSubscriber;

use App\Dto\CardAbilityFormDto;
use App\Dto\CreateCardFormDto;
use App\Form\CardAbilityType;
use App\Repository\AbilityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

readonly class CardAbilitiesFormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AbilityRepository $abilityRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
        ];
    }

    public function onPreSetData(FormEvent $event): void
    {
        $dto = $event->getData();
        $form = $event->getForm();

        if (!$dto) {
            return;
        }

        $allAbilities = $this->abilityRepository->findAll();

        // 1. Составляем карту УЖЕ привязанных способностей
        $existingMap = [];
        if (is_iterable($dto->abilitiesForm)) {
            foreach ($dto->abilitiesForm as $item) {
                // Вариант А: Элемент уже является DTO (например, после ошибки валидации)
                if ($item instanceof CardAbilityFormDto && $item->ability?->getId()) {
                    $existingMap[$item->ability->getId()] = $item;
                }
                // Вариант Б: Элемент является сущностью CardAbility (при редактировании из БД)
                elseif ($item instanceof \App\Entity\CardAbility && $item->getAbility()?->getId()) {
                    $existingMap[$item->getAbility()->getId()] = new CardAbilityFormDto(
                        ability: $item->getAbility(),
                        value: $item->getValue(),
                        enabled: true
                    );
                }
            }
        }

        // 2. Формируем полный список способностей
        $fullList = [];
        foreach ($allAbilities as $ability) {
            $abilityId = $ability->getId();

            if (isset($existingMap[$abilityId])) {
                $fullList[] = $existingMap[$abilityId];
            } else {
                $fullList[] = new CardAbilityFormDto(
                    ability: $ability,
                    value: null,
                    enabled: false
                );
            }
        }

        // 3. Записываем подготовленный массив обратно в DTO
        $dto->abilitiesForm = $fullList;

        // 4. Пересоздаем/обновляем поле формы с прокидыванием property_path
        $form->add('abilities', CollectionType::class, [
            'entry_type' => CardAbilityType::class,
            'property_path' => 'abilitiesForm',
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
    }
}