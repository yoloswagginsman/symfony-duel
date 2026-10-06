<?php

namespace App\Service;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Service\Output\IssuedApiToken;

/**
 * Выдача, проверка и отзыв непрозрачных API-токенов.
 * Токен: префикс + 64 случайных hex-символа; в базе хранится только его SHA-256.
 */
readonly class ApiTokenService
{
    // Префикс помогает узнать токен (в логах, в утечках) — как ghp_ у GitHub
    private const string PREFIX = 'duel_';

    /**
     * @param string $lifetime срок жизни токена, например '+30 days' (config/services.yaml)
     */
    public function __construct(
        private ApiTokenRepository $apiTokenRepository,
        private string $lifetime,
    ) {
    }

    public function issue(User $user): IssuedApiToken
    {
        $token = self::PREFIX . bin2hex(random_bytes(32));
        $apiToken = new ApiToken($user, $this->hash($token), new \DateTimeImmutable($this->lifetime));

        $this->apiTokenRepository->store($apiToken);

        return new IssuedApiToken($token, $apiToken->getExpiresAt());
    }

    /**
     * Действующий токен или null (нет такого или истёк).
     */
    public function findValid(#[\SensitiveParameter] string $token): ?ApiToken
    {
        $apiToken = $this->apiTokenRepository->findOneBy(['tokenHash' => $this->hash($token)]);

        return $apiToken !== null && !$apiToken->isExpired() ? $apiToken : null;
    }

    public function revoke(#[\SensitiveParameter] string $token): void
    {
        $apiToken = $this->apiTokenRepository->findOneBy(['tokenHash' => $this->hash($token)]);

        if ($apiToken !== null) {
            $this->apiTokenRepository->remove($apiToken);
        }
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
