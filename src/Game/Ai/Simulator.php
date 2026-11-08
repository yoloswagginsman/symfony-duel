<?php

namespace App\Game\Ai;

use App\Game\Action\StartGame;
use App\Game\Engine\GameEngine;
use App\Game\Model\CardDefinition;
use App\Game\Model\GameState;

/**
 * Партия компьютера с компьютером — для проверки баланса колод.
 */
final readonly class Simulator
{
    public function __construct(
        private GameEngine $engine,
        private ComputerPlayer $computerPlayer,
    ) {
    }

    /**
     * @param list<CardDefinition> $deck0 ходит первым
     * @param list<CardDefinition> $deck1
     * @param list<CardDefinition> $buildings
     * @param list<CardDefinition> $tokens    предметы, которые могут появиться по ходу партии
     *
     * @return array{winner: ?int, turns: int} winner — null, если за $maxTurns ходов победителя нет
     */
    public function play(array $deck0, array $deck1, array $buildings, int $seed, int $maxTurns, array $tokens = []): array
    {
        $state = GameState::create($deck0, $deck1, $buildings, $seed, $tokens);
        $this->engine->apply($state, new StartGame());

        while (!$state->isOver() && $state->turn <= $maxTurns) {
            $this->computerPlayer->playTurn($state, $state->activePlayer);
        }

        return ['winner' => $state->winner, 'turns' => $state->turn];
    }
}
