<?php

namespace App\Game\Model;

/**
 * Точка сопряжения: нейтральная постройка (случайная в каждой партии) и существо, которое её держит.
 * Бонус постройки — её способности; действует в пользу владельца держателя.
 */
final class CapturePoint
{
    public ?CardInstance $holder = null;

    public function __construct(
        public readonly CardInstance $building,
    ) {
    }

    /**
     * Игрок, который держит точку, или null — точка свободна.
     */
    public function controller(): ?int
    {
        return $this->holder?->owner;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['building' => $this->building->toArray(), 'holder' => $this->holder?->toArray()];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $point = new self(CardInstance::fromArray($data['building']));
        $point->holder = CardInstance::fromNullableArray($data['holder']);

        return $point;
    }
}
