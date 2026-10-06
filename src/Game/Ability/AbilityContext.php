<?php

namespace App\Game\Ability;

use App\Game\Engine\GameContext;
use App\Game\Model\CardInstance;

/**
 * Что знает способность в момент срабатывания: партия (и её действия), своя карта и число со способности.
 */
final readonly class AbilityContext
{
    public function __construct(
        public GameContext $game,
        public CardInstance $source,
        private ?int $value,
    ) {
    }

    /**
     * Число со способности на карте («Heal 3» → 3) или значение по умолчанию («Heal» → $default).
     */
    public function value(int $default): int
    {
        return $this->value ?? $default;
    }
}
