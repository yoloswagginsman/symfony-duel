<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\CapturePoint;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\PointCaptured;

final class CapturePointTest extends GameTestCase
{
    public function testCreatureCapturesPoint(): void
    {
        $state = $this->startGame();
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);

        $events = $this->play(new CapturePoint(0, $scout->id, 1));

        self::assertSame($scout, $state->capturePoints[1]->holder);
        self::assertNull($state->player(0)->board[0], 'ячейка поля освободилась');
        self::assertCount(1, $this->eventsOf($events, PointCaptured::class));
    }

    public function testCreatureOnPointCannotAttack(): void
    {
        $this->startGame();
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);
        $this->play(new CapturePoint(0, $scout->id, 0));
        $scout->actedThisTurn = false;

        $this->expectExceptionMessage('на точке сопряжения не атакует');
        $this->play(new Attack(0, $scout->id));
    }

    public function testOccupiedPointCannotBeCaptured(): void
    {
        $state = $this->startGame();
        $state->capturePoints[0]->holder = $this->state->newInstance($this->creature('Чужой', 1, 1, 1), 1);
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);

        $this->expectException(IllegalActionException::class);
        $this->play(new CapturePoint(0, $scout->id, 0));
    }

    public function testEnemyOnPointCanBeAttackedAndPointFrees(): void
    {
        $state = $this->startGame();
        $holder = $state->newInstance($this->creature('Держатель', 1, 1, 2), 1);
        $state->capturePoints[0]->holder = $holder;
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);

        $this->play(new Attack(0, $wolf->id, $holder->id));

        self::assertNull($state->capturePoints[0]->holder, 'держатель погиб — точка свободна');
    }

    public function testTauntDoesNotProtectPointHolder(): void
    {
        $state = $this->startGame();
        $this->summon(1, $this->creature('Страж', 1, 1, 5, ['Taunt']), 0);
        $holder = $state->newInstance($this->creature('Держатель', 1, 1, 2), 1);
        $state->capturePoints[0]->holder = $holder;
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);

        $this->play(new Attack(0, $wolf->id, $holder->id));

        self::assertNull($state->capturePoints[0]->holder, 'точку можно отбить и при Провокации соперника');
    }

    public function testTauntStillGuardsBoardAndFortification(): void
    {
        $this->startGame();
        $this->summon(1, $this->creature('Страж', 1, 1, 5, ['Taunt']), 0);
        $enemy = $this->summon(1, $this->creature('Враг', 1, 1, 2), 1);
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);

        $this->expectExceptionMessage('Мешает Провокация');
        $this->play(new Attack(0, $wolf->id, $enemy->id));
    }
}
