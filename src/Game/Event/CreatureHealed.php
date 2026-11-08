<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо восстановило здоровье (способность Heal).
 */
final readonly class CreatureHealed implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $amount,
        public ?CardInstance $source,
    ) {
    }
}
