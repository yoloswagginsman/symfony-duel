<?php

namespace App\Game\Event;

/**
 * Игрок сдался — следом GameWon соперника.
 */
final readonly class PlayerSurrendered implements GameEvent
{
    public function __construct(
        public int $player,
    ) {
    }
}
