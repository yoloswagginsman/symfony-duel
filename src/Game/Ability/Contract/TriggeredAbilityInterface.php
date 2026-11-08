<?php

namespace App\Game\Ability\Contract;

use App\Game\Ability\AbilityContext;
use App\Game\Event\GameEvent;

/**
 * Реагирует на события партии: «при розыгрыше», «при гибели», «в начале хода» и т.д.
 * Получает все события — сама решает, на какие отвечать (обычно: событие про свою карту).
 */
interface TriggeredAbilityInterface extends AbilityInterface
{
    public function onEvent(GameEvent $event, AbilityContext $context): void;
}
