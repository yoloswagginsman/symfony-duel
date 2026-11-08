<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо погибло и ушло в сброс.
 */
final readonly class CreatureDied implements GameEvent
{
    public function __construct(
        public CardInstance $card,
    ) {
    }
}
