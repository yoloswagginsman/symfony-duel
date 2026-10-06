<?php

namespace App\Tests\Game;

use App\Game\Action\EndTurn;
use App\Game\Action\Surrender;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\GameWon;
use App\Game\Event\PlayerSurrendered;

final class SurrenderTest extends GameTestCase
{
    public function testSurrenderInOpponentTurnEndsGame(): void
    {
        $state = $this->startGame();

        $events = $this->play(new Surrender(1));   // сейчас ход игрока 0

        self::assertSame(0, $state->winner);
        self::assertInstanceOf(PlayerSurrendered::class, $events[0]);
        self::assertEquals(new GameWon(0), $events[1]);
    }

    public function testNothingAfterSurrender(): void
    {
        $this->startGame();
        $this->play(new Surrender(0));

        $this->expectException(IllegalActionException::class);
        $this->play(new EndTurn(0));
    }
}
