<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Ландшафт снят: истёк срок или его развеяли ($source — карта, которая развеяла). Половина поля снова без ландшафта.
 */
final readonly class LandscapeEnded implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public ?CardInstance $source = null,
    ) {
    }
}
