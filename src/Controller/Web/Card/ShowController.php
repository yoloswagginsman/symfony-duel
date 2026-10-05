<?php

namespace App\Controller\Web\Card;

use App\Entity\Card;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShowController extends AbstractController
{
    #[Route(path: '/cards/{id}', name: 'app_cards_show', methods: ['GET'])]
    public function show(?Card $card = null): Response
    {
        if (!$card) {
            return $this->render('card/not_found.html.twig', [], new Response('', 404));
        }

        return $this->render('card/show.html.twig', [
            'card' => $card,
        ]);
    }
}