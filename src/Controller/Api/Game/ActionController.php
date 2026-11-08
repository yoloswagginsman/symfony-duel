<?php

namespace App\Controller\Api\Game;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Ход: {"type": "play_card"|"attack"|"capture_point"|"end_turn", …} → события и новое состояние.
 */
class ActionController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games/{id}/actions', name: 'api_games_action', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function act(Game $game, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        return $this->json($this->manager->act($game, $user, $request));
    }
}
