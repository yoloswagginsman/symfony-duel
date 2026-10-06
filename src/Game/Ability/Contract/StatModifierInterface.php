<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;

/**
 * Меняет атаку и здоровье карты. Считается заново при каждом обращении —
 * поэтому бонус может зависеть от поля прямо сейчас (число карт, их тип, раса).
 */
interface StatModifierInterface extends AbilityInterface
{
    public function attackBonus(AbilityContext $context): int;

    public function healthBonus(AbilityContext $context): int;
}
