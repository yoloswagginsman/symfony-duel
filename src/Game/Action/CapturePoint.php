<?php

namespace App\Game\Action;

/**
 * Перевести своё существо с поля на свободную точку сопряжения.
 */
final readonly class CapturePoint implements ActionInterface
{
    public function __construct(
        public int $player,
        public int $creatureId,
        public int $point,
    ) {
    }
}
