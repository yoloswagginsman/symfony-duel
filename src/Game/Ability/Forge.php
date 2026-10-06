<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\GameEvent;
use App\Game\Event\TurnStarted;

/**
 * Кузница (нейтральная постройка) N, по умолчанию 1: в начале хода владельца точки
 * куёт N предметов в его руку — по очереди Кинжал (+1 к атаке) и Щит (+1 к здоровью).
 * Описания предметов — в контенте (cards/item/neutral.yaml), в партию попадают при создании (GameState::$tokens).
 */
final readonly class Forge implements TriggeredAbilityInterface
{
    private const int DEFAULT_ITEMS = 1;

    // vendorCode предметов — по очереди
    private const array ITEMS = ['DUEL-ITE-NEU-KINZAL', 'DUEL-ITE-NEU-SIT'];

    public static function slug(): string
    {
        return 'Forge';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        $game = $context->game;
        $forge = $context->source;
        if (!$event instanceof TurnStarted || $event->player !== $game->controllerOf($forge)) {
            return;
        }

        for ($i = 0; $i < $context->value(self::DEFAULT_ITEMS); ++$i) {
            $next = $forge->state['next_item'] ?? 0;
            $game->createCard($event->player, self::ITEMS[$next % count(self::ITEMS)], $forge);
            $forge->state['next_item'] = $next + 1;
        }
    }
}
