<?php

namespace App\Game\Action;

/**
 * Атака существом: по существу противника ($targetId) или по его фортификации ($targetId = null).
 */
final readonly class Attack implements ActionInterface
{
    public function __construct(
        public int $player,
        public int $attackerId,
        public ?int $targetId = null,
    ) {
    }
}
