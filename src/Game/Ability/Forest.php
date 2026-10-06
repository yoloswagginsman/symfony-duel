<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\AuraAbilityInterface;
use App\Game\Model\CardInstance;

/**
 * Лес (ландшафт) N, по умолчанию 1: +N к здоровью лесным расам на своей половине поля.
 */
final readonly class Forest implements AuraAbilityInterface
{
    private const int DEFAULT_BONUS = 1;

    // slug рас из data/content/races.yaml
    private const array FOREST_RACES = ['elves', 'ancient-forest'];

    public static function slug(): string
    {
        return 'Forest';
    }

    public function attackBonusFor(CardInstance $target, AbilityContext $context): int
    {
        return 0;
    }

    public function healthBonusFor(CardInstance $target, AbilityContext $context): int
    {
        $side = $context->game->state->sideOf($context->source);
        if ($side === null || !$context->game->isOnBoardOf($target, $side)) {
            return 0;
        }

        return in_array($target->definition->race, self::FOREST_RACES, true) ? $context->value(self::DEFAULT_BONUS) : 0;
    }
}
