<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Лечение N (по умолчанию 2), разовый эффект: восстанавливает N здоровья всем своим раненым существам.
 * Фортификацию не лечит — для неё есть Repair.
 */
final readonly class Heal implements EffectAbilityInterface
{
    private const int DEFAULT_AMOUNT = 2;

    public static function slug(): string
    {
        return 'Heal';
    }

    public function apply(AbilityContext $context, ?CardInstance $target): void
    {
        $game = $context->game;
        foreach ($game->state->cardsInPlay($context->source->owner) as $creature) {
            $game->healCreature($creature, $context->value(self::DEFAULT_AMOUNT), $context->source);
        }
    }
}
