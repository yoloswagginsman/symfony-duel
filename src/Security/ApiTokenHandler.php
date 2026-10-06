<?php

namespace App\Security;

use App\Service\ApiTokenService;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Проверка API-токена из заголовка X-Api-Token (файрвол api, access_token).
 */
readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiTokenService $apiTokenService,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $apiToken = $this->apiTokenService->findValid($accessToken)
            ?? throw new BadCredentialsException('Invalid credentials.');

        return new UserBadge($apiToken->getUser()->getUserIdentifier());
    }
}
