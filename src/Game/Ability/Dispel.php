<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Развеять ландшафт, разовый эффект: цель — ландшафт любой половины поля;
 * без цели — ландшафт на своей половине (снять враждебный, например «Тёмный дождь»).
 */
final readonly class Dispel implements EffectAbilityInterface
{
    public static function slug(): string
    {
        return 'Dispel';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        $state = $context->game->state;
        $landscape = $target !== null
            ? $state->findLandscape($target->id)
            : $state->player($context->source->owner)->landscape;

        if ($landscape !== null) {
            $context->game->endLandscape($landscape, $context->source);
        }
    }
}
