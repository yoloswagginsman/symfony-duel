<?php

namespace App\Controller\Card;

use App\Entity\Card;
use App\Form\CardsType;
use App\Repository\AbilitiesRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EditController extends AbstractController
{
    #[Route(path: '/cards/{id}/edit', name: 'app_cards_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Card $card,
        EntityManagerInterface $entityManager,
        ImageUploader $imageUploader,
        AbilitiesRepository $abilitiesRepository
    ): Response {
        // Получаем все доступные способности
        $allAbilities = $abilitiesRepository->findAll();

        // Получаем текущие способности карты
        $currentAbilities = [];
        foreach ($card->getAbilities() as $cardAbility) {
            $currentAbilities[$cardAbility->getAbility()->getId()] = $cardAbility->getValue();
        }

        $form = $this->createForm(CardsType::class, $card);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Обработка изображения
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                // Если есть старое изображение - удаляем
                if ($card->getImagePath()) {
                    $imageUploader->remove($card->getImagePath());
                }

                $newPath = $imageUploader->upuvfload($card, $imageFile);
                $card->setImagePath($newPath);

                $this->addFlash('success', 'Изображение успешно обновлено!');
            }

            // Обработка способностей
            $abilityData = $request->request->all()['abilities'] ?? [];

            // Удаляем старые способности
            foreach ($card->getAbilities() as $cardAbility) {
                $entityManager->remove($cardAbility);
            }
            $card->getAbilities()->clear();

            // Добавляем новые способности
            foreach ($abilityData as $abilityId => $data) {
                if (isset($data['enabled']) && $data['enabled'] === '1') {
                    $ability = $abilitiesRepository->find($abilityId);
                    if ($ability) {
                        $value = isset($data['value']) && $data['value'] !== '' ? (int)$data['value'] : null;
                        $card->addAbilityWithValue($ability, $value);
                    }
                }
            }

            $card->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Карта успешно обновлена!');
            return $this->redirectToRoute('app_cards_show', ['id' => $card->getId()]);
        }

        return $this->render('cards/edit.html.twig', [
            'card' => $card,
            'form' => $form->createView(),
            'allAbilities' => $allAbilities,
            'currentAbilities' => $currentAbilities,
        ]);
    }
}