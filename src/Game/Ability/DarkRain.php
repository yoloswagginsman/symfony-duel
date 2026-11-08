<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\AuraAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Тёмный дождь (враждебный ландшафт, вместе с Hostile) N, по умолчанию 1:
 * −N к атаке существам на половине поля, где он лежит.
 */
final readonly class DarkRain implements AuraAbilityInterface
{
    private const int DEFAULT_PENALTY = 1;

    public static function slug(): string
    {
        return 'Dark_Rain';
    }

    public function attackBonusFor(CardInstance $target, AbilityContext $context): int
    {
        $side = $context->game->state->sideOf($context->source);

        return $side !== null && $context->game->isOnBoardOf($target, $side) ? -$context->value(self::DEFAULT_PENALTY) : 0;
    }

    public function healthBonusFor(CardInstance $target, AbilityContext $context): int
    {
        return 0;
    }
}
