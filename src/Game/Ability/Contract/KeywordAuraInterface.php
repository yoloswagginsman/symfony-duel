<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;
use App\Game\Enum\Keyword;
use App\Game\Model\CardInstance;

/**
 * Аура ключевых слов: даёт их другим картам, пока источник в игре
 * («Туман: существа на этой половине поля в Укрытии»). Считается заново при каждом обращении.
 */
interface KeywordAuraInterface extends AbilityInterface
{
    /**
     * @return list<Keyword>
     */
    public function keywordsFor(CardInstance $target, AbilityContext $context): array;
}
