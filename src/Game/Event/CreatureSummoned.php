<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Существо вышло на поле — из руки или вызванное другой картой. На нём срабатывает Призыв
 * (разовые эффекты существа); $target — цель, выбранная при розыгрыше.
 */
final readonly class CreatureSummoned implements GameEvent
{
    public function __construct(
        public CardInstance $card,
        public int $cell,
        public ?CardInstance $target = null,
    ) {
    }
}
