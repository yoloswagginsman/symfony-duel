<?php

namespace App\Game\Action;

/**
 * Разыграть карту из руки. Существу нужна свободная ячейка ($cell), заклинанию — нет.
 */
final readonly class PlayCard implements ActionInterface
{
    public function __construct(
        public int $player,
        public int $cardId,
        public ?int $cell = null,
        public ?int $targetId = null,
    ) {
    }
}
