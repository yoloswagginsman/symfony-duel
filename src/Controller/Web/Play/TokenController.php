<?php

namespace App\Controller\Web\Play;

use App\Entity\User;
use App\Enum\UserRole;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JWT для страницы партии: сайт работает на сессии, а API — на JWT.
 * Страница берёт токен здесь и повторяет запрос, когда он истекает (15 минут).
 * Ответ читает только сам сайт: чужие страницы не получат его без CORS.
 */
#[IsGranted(UserRole::PLAYER->value)]
class TokenController extends AbstractController
{
    public function __construct(private readonly JWTTokenManagerInterface $jwtManager)
    {
    }

    #[Route(path: '/play/token', name: 'app_play_token', methods: ['GET'])]
    public function token(#[CurrentUser] User $user): JsonResponse
    {
        $response = new JsonResponse(['token' => $this->jwtManager->create($user)]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
