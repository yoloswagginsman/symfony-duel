<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\PlayCard;
use App\Game\Action\StartGame;
use App\Game\Ai\ComputerPlayer;
use App\Game\Event\TurnEnded;
use App\Game\Model\GameState;

final class ComputerPlayerTest extends GameTestCase
{
    public function testFinishesOpponentWhenLethal(): void
    {
        $state = $this->startGame();
        $state->player(1)->fortification = 3;
        $this->summon(1, $this->creature('Огр', 1, 9, 9), 0);
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);

        $action = $this->ai()->bestAction($state, 0);

        self::assertEquals(new Attack(0, $wolf->id), $action, 'добить фортификацию важнее размена');
    }

    public function testPlaysCreatureIntoFreeCell(): void
    {
        $state = $this->startGame();
        $state->player(0)->hand = [];
        $knight = $this->giveToHand(0, $this->creature('Рыцарь', 1, 2, 2));

        self::assertEquals(new PlayCard(0, $knight->id, 0), $this->ai()->bestAction($state, 0));
    }

    public function testRespectsTauntThroughEngine(): void
    {
        $state = $this->startGame();
        $guard = $this->summon(1, $this->creature('Страж', 1, 1, 5, ['Taunt']), 0);
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);
        $state->player(0)->hand = [];

        self::assertEquals(new Attack(0, $wolf->id, $guard->id), $this->ai()->bestAction($state, 0), 'мимо Провокации движок не пустит');
    }

    public function testEndsTurnWhenNothingHelps(): void
    {
        $state = $this->startGame();
        $state->player(0)->hand = [];

        $events = $this->ai()->playTurn($state, 0);

        self::assertSame(1, $state->activePlayer);
        self::assertInstanceOf(TurnEnded::class, $events[0]);
    }

    public function testComputerVsComputerGameEnds(): void
    {
        $deck = [
            ...array_fill(0, 8, $this->creature('Волк', 2, 3, 2)),
            ...array_fill(0, 6, $this->creature('Страж', 3, 1, 5, ['Taunt'])),
            ...array_fill(0, 6, $this->creature('Всадник', 4, 4, 3, ['Charge'])),
        ];
        $this->state = GameState::create($deck, $deck, [$this->building('Кузница', ['Forge']), $this->building('Башня', ['Watchtower'])], seed: 3);
        $this->play(new StartGame());

        $ai = $this->ai();
        for ($turn = 0; $turn < 100 && !$this->state->isOver(); ++$turn) {
            $ai->playTurn($this->state, $this->state->activePlayer);
        }

        self::assertTrue($this->state->isOver(), 'партия двух компьютеров доходит до победы');
        self::assertLessThanOrEqual(0, $this->state->player($this->state->opponentOf($this->state->winner))->fortification);
    }

    private function ai(): ComputerPlayer
    {
        return new ComputerPlayer($this->engine, $this->registry());
    }
}
