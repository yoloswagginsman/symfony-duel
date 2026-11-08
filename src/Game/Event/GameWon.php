<?php

namespace App\Game\Event;

/**
 * Партия окончена.
 */
final readonly class GameWon implements GameEvent
{
    public function __construct(
        public int $winner,
    ) {
    }
}
