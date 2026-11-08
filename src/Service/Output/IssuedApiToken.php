<?php

namespace App\Service\Output;

/**
 * Только что выданный API-токен. $token — в открытом виде: показывается один раз.
 */
readonly class IssuedApiToken
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
