<?php

namespace App\Controller\Web\Cards;

use App\Repository\CardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    #[Route(path: '/cards', name: 'app_cards_index', methods: ['GET'])]
    public function index(CardRepository $cardsRepository): Response
    {
        return $this->render('cards/index.html.twig', [
            'cards' => $cardsRepository->findAll(),
        ]);
    }
}