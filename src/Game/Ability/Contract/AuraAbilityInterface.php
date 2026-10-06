<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;
use App\Game\Model\CardInstance;

/**
 * Аура: меняет атаку и здоровье других карт, пока источник в игре
 * («Кузница на точке: +1 к атаке существам владельца», «все ваши драконы +1»).
 * Как и StatModifierInterface, считается заново при каждом обращении.
 */
interface AuraAbilityInterface extends AbilityInterface
{
    public function attackBonusFor(CardInstance $target, AbilityContext $context): int;

    public function healthBonusFor(CardInstance $target, AbilityContext $context): int;
}
