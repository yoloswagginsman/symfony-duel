<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\KeywordAbilityInterface;
use App\Game\Enum\Keyword;

/**
 * Провокация: пока существо в игре, противник может атаковать только его.
 */
final readonly class Taunt implements KeywordAbilityInterface
{
    public static function slug(): string
    {
        return 'Taunt';
    }

    public function keywords(AbilityContext $context): array
    {
        return [Keyword::Taunt];
    }
}
