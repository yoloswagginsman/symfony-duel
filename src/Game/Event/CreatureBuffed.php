<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо усилено навсегда (пока живо): +атака и/или +здоровье — например, надет предмет.
 */
final readonly class CreatureBuffed implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $attack,
        public int $health,
        public ?CardInstance $source = null,
    ) {
    }
}
