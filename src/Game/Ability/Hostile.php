<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\KeywordAbilityInterface;
use App\Game\Enum\Keyword;

/**
 * Враждебный ландшафт: ложится не на свою половину поля, а на половину противника
 * (и вытесняет его ландшафт).
 */
final readonly class Hostile implements KeywordAbilityInterface
{
    public static function slug(): string
    {
        return 'Hostile';
    }

    public function keywords(AbilityContext $context): array
    {
        return [Keyword::Hostile];
    }
}
