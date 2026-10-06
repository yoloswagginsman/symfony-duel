<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Фортификация получила урон.
 */
final readonly class FortificationDamaged implements GameEvent
{
    public function __construct(
        public int $player,
        public int $amount,
        public ?CardInstance $source,
    ) {
    }
}
