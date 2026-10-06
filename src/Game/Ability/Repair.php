<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Ремонт N (по умолчанию 2), разовый эффект: восстанавливает фортификацию владельца.
 */
final readonly class Repair implements EffectAbilityInterface
{
    private const int DEFAULT_AMOUNT = 2;

    public static function slug(): string
    {
        return 'Repair';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        $context->game->repairFortification($context->source->owner, $context->value(self::DEFAULT_AMOUNT), $context->source);
    }
}
