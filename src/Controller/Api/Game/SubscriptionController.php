<?php

namespace App\Controller\Api\Game;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Подписка на события своей партии в реальном времени (Mercure): {"hub", "topic", "token"}.
 * Клиент: GET {hub}?match={topic} с заголовком Authorization: Bearer {token} — поток SSE.
 */
class SubscriptionController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/api/games/{id}/subscription', name: 'api_games_subscription', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function subscription(Game $game, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->manager->subscription($game, $user));
    }
}
