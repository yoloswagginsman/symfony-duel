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
 * Присоединиться к партии: {"deckId"} → партия начинается.
 */
class JoinController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games/{id}/join', name: 'api_games_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Game $game, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        return $this->json($this->manager->join($game, $user, $request));
    }
}
