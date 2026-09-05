<?php

namespace App\Controller\Card;

use App\Entity\Card;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShowController extends AbstractController
{
    #[Route(path: '/cards/{id}', name: 'app_cards_show', methods: ['GET'])]
    public function show(Card $card): Response
    {
        return $this->render('cards/show.html.twig', [
            'card' => $card,
        ]);
    }
}