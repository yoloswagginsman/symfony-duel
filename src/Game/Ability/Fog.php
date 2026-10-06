<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\KeywordAuraInterface;
use App\Game\Enum\Keyword;
use App\Game\Model\CardInstance;

/**
 * Туман (ландшафт), как теневая линия в TES: Legends — существа в ячейках этой половины поля
 * в Укрытии: их нельзя атаковать. Обычно вместе с Duration 1: прикрывает на ход противника.
 * Существо с Провокацией туман не прячет.
 */
final readonly class Fog implements KeywordAuraInterface
{
    public static function slug(): string
    {
        return 'Fog';
    }

    public function keywordsFor(CardInstance $target, AbilityContext $context): array
    {
        $side = $context->game->state->sideOf($context->source);

        return $side !== null && $context->game->isOnBoardOf($target, $side) ? [Keyword::Cover] : [];
    }
}
