<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\GameEvent;
use App\Game\Event\TurnStarted;

/**
 * Сторожевая башня (нейтральная постройка) N, по умолчанию 1:
 * в начале хода владельца точки — N урона фортификации противника.
 */
final readonly class Watchtower implements TriggeredAbilityInterface
{
    private const int DEFAULT_DAMAGE = 1;

    public static function slug(): string
    {
        return 'Watchtower';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        $game = $context->game;
        if ($event instanceof TurnStarted && $event->player === $game->controllerOf($context->source)) {
            $game->damageFortification($game->state->opponentOf($event->player), $context->value(self::DEFAULT_DAMAGE), $context->source);
        }
    }
}
