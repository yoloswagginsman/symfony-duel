<?php

namespace App\Game\Ai;

use App\Game\Ability\AbilityRegistry;
use App\Game\Action\ActionInterface;
use App\Game\Action\Attack;
use App\Game\Action\CapturePoint;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Engine\GameContext;
use App\Game\Engine\GameEngine;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\GameEvent;
use App\Game\GameRules;
use App\Game\Model\CardInstance;
use App\Game\Model\GameState;

/**
 * Компьютерный соперник — жадный, на один ход вперёд. Правил не повторяет: каждый возможный ход
 * пробует на копии партии через GameEngine (недопустимый движок отклонит), оценивает позицию
 * и делает лучший. Ни один ход позицию не улучшает — конец хода.
 */
final readonly class ComputerPlayer
{
    // Защита от зацикливания: больше действий за ход не бывает
    private const int MAX_ACTIONS_PER_TURN = 40;

    // Оценка позиции
    private const int WIN = 10000;
    private const int POINT_VALUE = 2;   // удерживать точку сопряжения — как существо 1/1

    public function __construct(
        private GameEngine $engine,
        private AbilityRegistry $abilities,
    ) {
    }

    /**
     * Сыграть весь ход за игрока $player (сейчас его ход).
     *
     * @param (\Closure(list<GameEvent>): void)|null $afterAction вызывается после каждого действия —
     *                                                              например, запомнить поле для пошагового показа
     *
     * @return list<GameEvent>
     */
    public function playTurn(GameState $state, int $player, ?\Closure $afterAction = null): array
    {
        $events = [];
        $apply = function (ActionInterface $action) use ($state, $afterAction, &$events): void {
            $actionEvents = $this->engine->apply($state, $action);
            array_push($events, ...$actionEvents);
            $afterAction?->__invoke($actionEvents);
        };

        for ($i = 0; $i < self::MAX_ACTIONS_PER_TURN && !$state->isOver(); ++$i) {
            $action = $this->bestAction($state, $player);
            $apply($action);

            if ($action instanceof EndTurn) {
                return $events;
            }
        }

        if (!$state->isOver() && $state->activePlayer === $player) {
            $apply(new EndTurn($player));
        }

        return $events;
    }

    /**
     * Лучший ход по оценке позиции после него; если ни один не лучше текущей — конец хода.
     */
    public function bestAction(GameState $state, int $player): ActionInterface
    {
        $best = new EndTurn($player);
        $bestScore = $this->score($state, $player);

        foreach ($this->candidates($state, $player) as $action) {
            $copy = GameState::fromArray($state->toArray());
            try {
                $this->engine->apply($copy, $action);
            } catch (IllegalActionException) {
                continue;
            }

            $score = $this->score($copy, $player);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$action, $score];
            }
        }

        return $best;
    }

    /**
     * Оценка партии для игрока: фортификации, существа в игре (атака + здоровье с учётом способностей),
     * точки сопряжения. Своё — плюсом, чужое — минусом.
     */
    public function score(GameState $state, int $player): int
    {
        if ($state->isOver()) {
            return $state->winner === $player ? self::WIN : -self::WIN;
        }

        $game = new GameContext($state, $this->abilities);
        $opponent = $state->opponentOf($player);
        $score = $state->player($player)->fortification - $state->player($opponent)->fortification;

        foreach ($state->cardsInPlay() as $creature) {
            $value = $game->attack($creature) + $game->health($creature);
            $score += $creature->owner === $player ? $value : -$value;
        }
        foreach ($state->capturePoints as $point) {
            $controller = $point->controller();
            if ($controller !== null) {
                $score += $controller === $player ? self::POINT_VALUE : -self::POINT_VALUE;
            }
        }

        return $score;
    }

    /**
     * Все ходы, которые стоит попробовать; допустимость проверит движок.
     *
     * @return list<ActionInterface>
     */
    private function candidates(GameState $state, int $player): array
    {
        $me = $state->player($player);
        $opponent = $state->opponentOf($player);
        $enemyIds = array_map(static fn (CardInstance $card) => $card->id, $state->cardsInPlay($opponent));
        $ownIds = array_map(static fn (CardInstance $card) => $card->id, $state->cardsInPlay($player));
        $landscapeIds = array_map(static fn (CardInstance $card) => $card->id, array_filter([$me->landscape, $state->player($opponent)->landscape]));
        $freeCell = array_search(null, $me->board, true);
        $actions = [];

        // Розыгрыш: без цели, по существу противника, по своему (предметы), по ландшафту (Развеять)
        foreach ($me->hand as $card) {
            if ($card->definition->manaCost > $me->mana) {
                continue;
            }
            foreach ([null, ...$enemyIds, ...$ownIds, ...$landscapeIds] as $targetId) {
                $actions[] = new PlayCard($player, $card->id, $freeCell === false ? null : $freeCell, $targetId);
            }
        }

        foreach ($me->board as $creature) {
            if ($creature === null || $creature->actedThisTurn) {
                continue;
            }
            // Атака: по фортификации и по каждому существу противника
            foreach ([null, ...$enemyIds] as $targetId) {
                $actions[] = new Attack($player, $creature->id, $targetId);
            }
            // Захват свободной точки
            foreach ($state->capturePoints as $index => $point) {
                if ($point->holder === null) {
                    $actions[] = new CapturePoint($player, $creature->id, $index);
                }
            }
        }

        return $actions;
    }
}
