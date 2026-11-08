<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Урон N (по умолчанию 1), разовый эффект: N урона выбранному существу,
 * без цели — фортификации противника. «Призыв: нанести 2 урона» — существо с Damage 2.
 */
final readonly class Damage implements EffectAbilityInterface
{
    private const int DEFAULT_DAMAGE = 1;

    public static function slug(): string
    {
        return 'Damage';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        $game = $context->game;
        $amount = $context->value(self::DEFAULT_DAMAGE);

        if ($target !== null) {
            $game->damageCreature($target, $amount, $context->source);
        } else {
            $game->damageFortification($game->state->opponentOf($context->source->owner), $amount, $context->source);
        }
    }
}
