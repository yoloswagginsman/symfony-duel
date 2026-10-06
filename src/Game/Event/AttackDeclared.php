<?php

namespace App\Game\Event;

use App\Game\Model\CardInstance;

/**
 * Объявлена атака. $target = null — атака по фортификации.
 */
final readonly class AttackDeclared implements GameEvent
{
    public function __construct(
        public CardInstance $attacker,
        public ?CardInstance $target,
    ) {
    }
}
