<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо получило урон (уже после щита и т.п.).
 */
final readonly class CreatureDamaged implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $amount,
        public ?CardInstance $source,
    ) {
    }
}
