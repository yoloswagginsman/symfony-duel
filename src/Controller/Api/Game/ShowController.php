<?php

namespace App\Controller\Api\Game;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Партия глазами игрока — только для участников.
 */
class ShowController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games/{id}', name: 'api_games_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Game $game, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->manager->show($game, $user));
    }
}
