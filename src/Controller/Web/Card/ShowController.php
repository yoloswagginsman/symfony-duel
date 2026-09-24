<?php

namespace App\Controller\Web\Card;

use App\Entity\Card;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

class ShowController extends AbstractController
{
    /**
     * Несуществующая карта → 404 от резолвера сущности,
     * страница — templates/bundles/TwigBundle/Exception/error404.html.twig
     */
    #[Route(path: '/cards/{id}', name: 'app_cards_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Card $card): Response
    {
        return $this->render('card/show.html.twig', [
            'card' => $card,
        ]);
    }
}
