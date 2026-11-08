<?php

namespace App\Security;

use App\Entity\User;
use App\Service\ApiTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * POST /api/tokens: json_login проверил email и пароль — выдаём новый API-токен.
 */
readonly class ApiTokenIssueHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private ApiTokenService $apiTokenService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        /** @var User $user */
        $user = $token->getUser();
        $issued = $this->apiTokenService->issue($user);

        return new JsonResponse([
            'token' => $issued->token,
            'expiresAt' => $issued->expiresAt->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }
}
