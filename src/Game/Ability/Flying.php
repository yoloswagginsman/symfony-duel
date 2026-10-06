<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\KeywordAbilityInterface;
use App\Game\Enum\Keyword;

/**
 * Полёт: атаковать это существо может только другое летающее.
 */
final readonly class Flying implements KeywordAbilityInterface
{
    public static function slug(): string
    {
        return 'Flying';
    }

    public function keywords(AbilityContext $context): array
    {
        return [Keyword::Flying];
    }
}
