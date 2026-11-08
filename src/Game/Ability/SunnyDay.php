<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\AuraAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Солнечный день (ландшафт) N, по умолчанию 1: +N к атаке существам на своей половине поля.
 */
final readonly class SunnyDay implements AuraAbilityInterface
{
    private const int DEFAULT_BONUS = 1;

    public static function slug(): string
    {
        return 'Sunny_Day';
    }

    public function attackBonusFor(CardInstance $target, AbilityContext $context): int
    {
        $side = $context->game->state->sideOf($context->source);

        return $side !== null && $context->game->isOnBoardOf($target, $side) ? $context->value(self::DEFAULT_BONUS) : 0;
    }

    public function healthBonusFor(CardInstance $target, AbilityContext $context): int
    {
        return 0;
    }
}
