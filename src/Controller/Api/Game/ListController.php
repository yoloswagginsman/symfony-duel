<?php

namespace App\Controller\Api\Game;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Партии, к которым можно присоединиться, и свои незавершённые.
 */
class ListController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games', name: 'api_games_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->manager->list($user));
    }
}
