<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\CreatureDied;
use App\Game\Event\GameWon;
use App\Game\GameRules;

final class CombatTest extends GameTestCase
{
    public function testPlayCreatureSpendsManaAndTakesCell(): void
    {
        $state = $this->startGame();
        $wolf = $this->giveToHand(0, $this->creature('Волк', 1, 2, 2));

        $this->play(new PlayCard(0, $wolf->id, cell: 2));

        self::assertSame($wolf, $state->player(0)->board[2]);
        self::assertSame(0, $state->player(0)->mana);
        self::assertNotContains($wolf, $state->player(0)->hand);
    }

    public function testNotEnoughMana(): void
    {
        $this->startGame();
        $giant = $this->giveToHand(0, $this->creature('Великан', 7, 7, 7));

        $this->expectExceptionMessage('Не хватает маны');
        $this->play(new PlayCard(0, $giant->id, cell: 0));
    }

    public function testSummonedCreatureCannotAttackSameTurn(): void
    {
        $this->startGame();
        $wolf = $this->giveToHand(0, $this->creature('Волк', 1, 2, 2));
        $this->play(new PlayCard(0, $wolf->id, cell: 0));

        $this->expectException(IllegalActionException::class);
        $this->play(new Attack(0, $wolf->id));
    }

    public function testAttackFortification(): void
    {
        $state = $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 2), 0);

        $this->play(new Attack(0, $wolf->id));

        self::assertSame(GameRules::FORTIFICATION - 3, $state->player(1)->fortification);
    }

    public function testCreatureAttacksOncePerTurn(): void
    {
        $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 2), 0);
        $this->play(new Attack(0, $wolf->id));

        $this->expectExceptionMessage('уже действовало');
        $this->play(new Attack(0, $wolf->id));
    }

    public function testCombatDamagesBothAndKills(): void
    {
        $state = $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 2), 0);
        $bear = $this->summon(1, $this->creature('Медведь', 1, 1, 3), 0);

        $events = $this->play(new Attack(0, $wolf->id, $bear->id));

        self::assertNull($state->player(1)->board[0], 'медведь 1/3 получил 3 и погиб');
        self::assertContains($bear, $state->player(1)->graveyard);
        self::assertSame($wolf, $state->player(0)->board[0], 'волк 3/2 получил 1 и выжил');
        self::assertSame(1, $this->engineHealth($wolf));
        self::assertCount(1, $this->eventsOf($events, CreatureDied::class));
    }

    public function testWinWhenFortificationFalls(): void
    {
        $state = $this->startGame();
        $state->player(1)->fortification = 3;
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 2), 0);

        $events = $this->play(new Attack(0, $wolf->id));

        self::assertSame(0, $state->winner);
        self::assertCount(1, $this->eventsOf($events, GameWon::class));

        $this->expectExceptionMessage('Партия окончена');
        $this->play(new EndTurn(0));
    }

    public function testIllegalActionChangesNothing(): void
    {
        $state = $this->startGame();
        $giant = $this->giveToHand(0, $this->creature('Великан', 7, 7, 7));
        $handBefore = $state->player(0)->hand;

        try {
            $this->play(new PlayCard(0, $giant->id, cell: 0));
        } catch (IllegalActionException) {
        }

        self::assertSame($handBefore, $state->player(0)->hand);
        self::assertSame(1, $state->player(0)->mana);
        self::assertNull($state->player(0)->board[0]);
    }

    private function engineHealth(\App\Game\Model\CardInstance $card): int
    {
        return ($card->definition->health ?? 0) - $card->damage;
    }
}
