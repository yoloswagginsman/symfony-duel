<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\GameEvent;
use App\Game\Event\TurnStarted;

/**
 * Колодец маны (нейтральная постройка) N, по умолчанию 1:
 * в начале хода владельца точки — +N маны на этот ход.
 */
final readonly class ManaWell implements TriggeredAbilityInterface
{
    private const int DEFAULT_MANA = 1;

    public static function slug(): string
    {
        return 'Mana_Well';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        if ($event instanceof TurnStarted && $event->player === $context->game->controllerOf($context->source)) {
            $context->game->gainMana($event->player, $context->value(self::DEFAULT_MANA), $context->source);
        }
    }
}
