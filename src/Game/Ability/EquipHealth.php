<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Доспех N (по умолчанию 1), предмет: +N к здоровью своему существу — навсегда, пока оно живо.
 */
final readonly class EquipHealth implements EffectAbilityInterface
{
    private const int DEFAULT_BONUS = 1;

    public static function slug(): string
    {
        return 'Equip_Health';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        if ($target !== null) {
            $context->game->equip($target, $context->source, attack: 0, health: $context->value(self::DEFAULT_BONUS));
        }
    }
}
