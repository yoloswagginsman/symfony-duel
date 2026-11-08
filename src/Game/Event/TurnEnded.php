<?php

namespace App\Game\Event;

/**
 * Ход закончился.
 */
final readonly class TurnEnded implements GameEvent
{
    public function __construct(
        public int $player,
    ) {
    }
}
