<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Карта появилась в руке не из колоды — её создали (например, предмет из Кузницы).
 */
final readonly class CardCreated implements GameEvent
{
    public function __construct(
        public int $player,
        public CardInstance $card,
        public ?CardInstance $source = null,
    ) {
    }
}
