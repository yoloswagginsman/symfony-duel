<?php

namespace App\Controller\Card;

use App\Dto\CreateCardFormDto;
use App\Factory\CardFactory;
use App\Form\CardsType;
use App\Repository\AbilitiesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CreateController extends AbstractController
{
    #[Route(path: '/cards/new', name: 'app_cards_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CardFactory $cardFactory,
        AbilitiesRepository $abilitiesRepository
    ): Response {
        $dto = new CreateCardFormDto();
        $form = $this->createForm(CardsType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $card = $cardFactory->createFromFormDto($dto);

            $entityManager->persist($card);
            $entityManager->flush();

            $this->addFlash('success', 'Карта успешно создана!');
            return $this->redirectToRoute('app_cards_show', ['id' => $card->getId()]);
        }

        return $this->render('cards/new.html.twig', [
            'form' => $form->createView(),
            'card' => $dto,
            'allAbilities' => $abilitiesRepository->findAll(),
        ]);
    }
}