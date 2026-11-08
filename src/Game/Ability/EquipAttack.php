<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Оружие N (по умолчанию 1), предмет: +N к атаке своему существу — навсегда, пока оно живо.
 */
final readonly class EquipAttack implements EffectAbilityInterface
{
    private const int DEFAULT_BONUS = 1;

    public static function slug(): string
    {
        return 'Equip_Attack';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        if ($target !== null) {
            $context->game->equip($target, $context->source, attack: $context->value(self::DEFAULT_BONUS), health: 0);
        }
    }
}
