<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Урон по всем N (по умолчанию 1), разовый эффект: бьёт всех существ противника — на поле и на точках.
 */
final readonly class AoeDamage implements EffectAbilityInterface
{
    private const int DEFAULT_DAMAGE = 1;

    public static function slug(): string
    {
        return 'AoE_Damage';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        $game = $context->game;
        $enemies = $game->state->cardsInPlay($game->state->opponentOf($context->source->owner));
        foreach ($enemies as $enemy) {
            $game->damageCreature($enemy, $context->value(self::DEFAULT_DAMAGE), $context->source);
        }
    }
}
