<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Фортификацию отремонтировали (способность Repair).
 */
final readonly class FortificationRepaired implements GameEvent
{
    public function __construct(
        public int $player,
        public int $amount,
        public ?CardInstance $source,
    ) {
    }
}
