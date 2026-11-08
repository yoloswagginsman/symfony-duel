<?php

namespace App\Service\Game\Output;

use App\Entity\Game;
use App\Game\Engine\Step;

/**
 * Партия началась: сама партия и шаги старта (раздача, ход компьютера, если он первый).
 */
readonly class GameStarted
{
    /**
     * @param list<Step> $steps
     */
    public function __construct(
        public Game $game,
        public array $steps,
    ) {
    }
}
