<?php

namespace App\Game\Action;

final readonly class EndTurn implements ActionInterface
{
    public function __construct(
        public int $player,
    ) {
    }
}
