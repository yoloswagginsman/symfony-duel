<?php

namespace App\Controller\Api\Game;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Игра с компьютером: {"deckId"} → партия сразу начинается; ходы — как обычно, POST /api/games/{id}/actions.
 */
class ComputerController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games/computer', name: 'api_games_computer', methods: ['POST'])]
    public function play(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        return $this->json($this->manager->playComputer($user, $request), Response::HTTP_CREATED);
    }
}
