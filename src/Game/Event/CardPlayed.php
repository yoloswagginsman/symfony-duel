<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Карта разыграна из руки — любая: существо или заклинание.
 */
final readonly class CardPlayed implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public ?CardInstance $target = null,
    ) {
    }
}
