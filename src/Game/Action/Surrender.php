<?php

namespace App\Game\Action;

/**
 * Сдаться — можно в любой момент партии, не только в свой ход.
 */
final readonly class Surrender implements ActionInterface
{
    public function __construct(
        public int $player,
    ) {
    }
}
