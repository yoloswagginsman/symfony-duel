<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Разыгран ландшафт на половину поля игрока $side. $previous — ландшафт этой половины, который он заменил (ушёл в сброс).
 */
final readonly class LandscapeChanged implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $side,
        public ?CardInstance $previous,
    ) {
    }
}
