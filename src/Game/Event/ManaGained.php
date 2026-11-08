<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Игрок получил дополнительную ману на этот ход (сверх обычной).
 */
final readonly class ManaGained implements GameEvent
{
    public function __construct(
        public int $player,
        public int $amount,
        public ?CardInstance $source,
    ) {
    }
}
