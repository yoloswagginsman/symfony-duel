<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\KeywordAbilityInterface;
use App\Game\Enum\Keyword;

/**
 * Рывок: атакует в ход выкладки.
 */
final readonly class Charge implements KeywordAbilityInterface
{
    public static function slug(): string
    {
        return 'Charge';
    }

    public function keywords(AbilityContext $context): array
    {
        return [Keyword::Charge];
    }
}
