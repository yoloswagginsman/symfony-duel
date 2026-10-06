<?php

namespace App\Controller\Api\Token;

use App\Service\ApiTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Отзыв API-токена, которым подписан запрос (X-Api-Token).
 */
class RevokeController extends AbstractController
{
    public function __construct(private readonly ApiTokenService $apiTokenService)
    {
    }

    #[Route(path: '/api/tokens/current', name: 'api_tokens_revoke', methods: ['DELETE'])]
    public function revoke(Request $request): Response
    {
        $token = $request->headers->get('X-Api-Token');
        if ($token === null) {
            return new JsonResponse(['error' => 'Запрос должен быть подписан API-токеном (X-Api-Token).'], Response::HTTP_BAD_REQUEST);
        }

        $this->apiTokenService->revoke($token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
