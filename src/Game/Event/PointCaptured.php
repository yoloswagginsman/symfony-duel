<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо заняло точку сопряжения.
 */
final readonly class PointCaptured implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $point,
    ) {
    }
}
