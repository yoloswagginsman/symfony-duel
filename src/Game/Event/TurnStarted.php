<?php

namespace App\Game\Event;

/**
 * Ход начался (после прибавки маны, до добора карты).
 */
final readonly class TurnStarted implements GameEvent
{
    public function __construct(
        public int $player,
        public int $turn,
    ) {
    }
}
