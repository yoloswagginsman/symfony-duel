<?php

namespace App\Controller\Cards;

use App\Repository\CardsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    #[Route(path: '/cards', name: 'app_cards_index', methods: ['GET'])]
    public function index(CardsRepository $cardsRepository): Response
    {
        return $this->render('cards/list/hero/index.html.twig', [
            'cards' => $cardsRepository->findAll(),
        ]);
    }
}