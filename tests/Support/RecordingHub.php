<?php

namespace App\Tests\Support;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * Хаб Mercure для тестов (when@test в config/services.yaml): ничего не отправляет, запоминает события.
 */
final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    private TokenFactoryInterface $factory;

    public function __construct(
        private readonly string $publicUrl,
        string $secret,
        string $issuer,
    ) {
        // Токены — как у настоящего хаба (config/packages/mercure.yaml): протокол 1.0, те же claims
        $this->factory = new DefaultClaimsTokenFactory(
            new LcobucciFactory($secret, protocolVersion: ProtocolVersion::V1),
            ['iss' => $issuer, 'sub' => 'symfony-duel', 'client_id' => 'symfony-duel', 'aud' => $publicUrl],
        );
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->factory;
    }

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:' . count($this->updates);
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::V1;
    }

    public function getCookieName(): string
    {
        return '__Secure-mercure_access_token';
    }
}
