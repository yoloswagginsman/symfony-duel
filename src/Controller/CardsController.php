<?php

namespace App\Controller;

use App\Entity\Cards;
use App\Form\CardsType;
use App\Repository\AbilitiesRepository;
use App\Repository\CardsRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cards')]
final class CardsController extends AbstractController
{
    #[Route(name: 'app_cards_index', methods: ['GET'])]
    public function index(CardsRepository $cardsRepository): Response
    {
        return $this->render('cards/index.html.twig', [
            'cards' => $cardsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_cards_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageUploader $imageUploader,
        AbilitiesRepository $abilitiesRepository
    ): Response {
        $card = new Cards();
        $form = $this->createForm(CardsType::class, $card);
        $form->handleRequest($request);

        $allAbilities = $abilitiesRepository->findAll();

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newPath = $imageUploader->upload($card, $imageFile);
                $card->setImagePath($newPath);
            }

            $card->setCreatedAt(new \DateTime());
            $card->setUpdatedAt(new \DateTime());

            $entityManager->persist($card);
            $entityManager->flush();

            $this->addFlash('success', 'Карта успешно создана!');
            return $this->redirectToRoute('app_cards_show', ['id' => $card->getId()]);
        }

        return $this->render('cards/new.html.twig', [
            'card' => $card,
            'form' => $form->createView(),
            'allAbilities' => $allAbilities,
        ]);
    }

    #[Route('/{id}', name: 'app_cards_show', methods: ['GET'])]
    public function show(Cards $card): Response
    {
        return $this->render('cards/show.html.twig', [
            'card' => $card,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_cards_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Cards $card,
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

                $newPath = $imageUploader->upload($card, $imageFile);
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

    #[Route('/{id}', name: 'app_cards_delete', methods: ['POST'])]
    public function delete(Request $request, Cards $card, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$card->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($card);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_cards_index', [], Response::HTTP_SEE_OTHER);
    }
}
