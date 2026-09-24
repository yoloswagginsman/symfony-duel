<?php

namespace App\Form;

use App\Dto\CardAbilityFormDto;
use App\Dto\CreateCardFormDto;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use App\Form\EventSubscriber\CardAbilitiesFormSubscriber;
use App\Repository\AbilityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class CardsType extends AbstractType
{
    public function __construct(
        private readonly CardAbilitiesFormSubscriber $abilitiesSubscriber
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Название карты',
                'attr' => ['placeholder' => 'Введите название'],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
                'attr' => ['rows' => 4, 'placeholder' => 'Введите описание'],
                'required' => false,
            ])
            ->add('manaCost', NumberType::class, [
                'label' => 'Стоимость маны',
                'invalid_message' => 'card.mana.not_a_number',
                'attr' => ['min' => 0, 'max' => 10],
                'required' => true,
            ])
            ->add('attack', NumberType::class, [
                'label' => 'Атака',
                'invalid_message' => 'card.attack.not_a_number',
                'attr' => ['min' => 0],
                'required' => false,
            ])
            ->add('health', NumberType::class, [
                'label' => 'Здоровье',
                'invalid_message' => 'card.health.not_a_number',
                'attr' => ['min' => 0],
                'required' => false,
            ])
            ->add('cardType', EntityType::class, [
                'class' => CardType::class,
                'choice_label' => 'name',
                'label' => 'Тип карты',
                'placeholder' => 'Выберите тип',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('race', EntityType::class, [
                'class' => Race::class,
                'choice_label' => 'name',
                'label' => 'Раса',
                'placeholder' => 'Выберите расу',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('rarity', EntityType::class, [
                'class' => Rarity::class,
                'choice_label' => 'name',
                'label' => 'Редкость',
                'placeholder' => 'Выберите редкость',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Теги',
                'attr' => ['class' => 'form-select'],
                'required' => false,
            ])
            ->add('abilities', CollectionType::class, [
                'entry_type' => CardAbilityType::class,
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
                'property_path' => 'abilitiesForm',
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Изображение карты',
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        'maxSizeMessage' => 'Файл слишком большой (макс. 5 МБ)',
                        'mimeTypesMessage' => 'Пожалуйста, загрузите изображение',
                    ])
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Карта активна',
                'required' => false,
            ]);

        $builder->addEventSubscriber($this->abilitiesSubscriber);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateCardFormDto::class,
            'empty_data' => new CreateCardFormDto(),
            'isNew' => false,
        ]);
    }
}