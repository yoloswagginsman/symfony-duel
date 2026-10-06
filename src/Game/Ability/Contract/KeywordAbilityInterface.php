<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;
use App\Game\Enum\Keyword;

/**
 * Даёт карте ключевые слова, которые проверяют правила (Провокация, Рывок, Полёт).
 * Может давать их по условию — список считается заново при каждом обращении.
 */
interface KeywordAbilityInterface extends AbilityInterface
{
    /**
     * @return list<Keyword>
     */
    public function keywords(AbilityContext $context): array;
}
