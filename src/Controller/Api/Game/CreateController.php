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
 * Создать партию: {"deckId"} → ждёт второго игрока.
 */
class CreateController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games', name: 'api_games_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        return $this->json($this->manager->create($user, $request), Response::HTTP_CREATED);
    }
}
