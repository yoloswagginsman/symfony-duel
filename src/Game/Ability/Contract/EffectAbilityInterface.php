<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;
use App\Game\Model\CardInstance;

/**
 * Разовый эффект карты («нанести 2 урона», «вылечить», «развеять ландшафт»).
 * Срабатывает: у заклинания — при розыгрыше, у существа — при Призыве (выходе на поле:
 * из руки или вызванного другой картой). Один класс — и «Заклинание: …», и «Призыв: …».
 */
interface EffectAbilityInterface extends AbilityInterface
{
    /**
     * @param CardInstance|null $target цель, выбранная при розыгрыше (существо или ландшафт)
     */
    public function apply(AbilityContext $context, ?CardInstance $target): void;
}
