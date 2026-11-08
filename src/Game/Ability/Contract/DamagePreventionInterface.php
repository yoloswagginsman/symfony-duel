<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;

/**
 * Перехватывает урон по своей карте до того, как он нанесён.
 */
interface DamagePreventionInterface extends AbilityInterface
{
    /**
     * @return int сколько урона остаётся после способности
     */
    public function absorb(int $damage, AbilityContext $context): int;
}
