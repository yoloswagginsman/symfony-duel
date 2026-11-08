<?php

namespace App\Game\Engine;

use App\Game\Event\GameEvent;

/**
 * Одно действие партии и поле после него — чтобы клиент проигрывал ход соперника по шагам,
 * а не перескакивал сразу к итогу.
 */
final readonly class Step
{
    /**
     * @param list<GameEvent>      $events
     * @param array<string, mixed> $state  GameState::toArray() после действия
     * @param int|null             $actor  чей был ход перед действием; null — старт партии
     */
    public function __construct(
        public array $events,
        public array $state,
        public ?int $actor = null,
    ) {
    }

    /**
     * @param list<Step> $steps
     *
     * @return list<GameEvent> события всех шагов подряд
     */
    public static function eventsOf(array $steps): array
    {
        return array_merge([], ...array_map(static fn (self $step) => $step->events, $steps));
    }
}
