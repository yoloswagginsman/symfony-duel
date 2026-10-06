<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Игрок взял карту из колоды.
 */
final readonly class CardDrawn implements GameEvent
{
    public function __construct(
        public int $player,
        public CardInstance $card,
    ) {
    }
}
