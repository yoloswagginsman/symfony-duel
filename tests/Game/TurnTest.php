<?php

namespace App\Tests\Game;

use App\Game\Action\EndTurn;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\CardDrawn;
use App\Game\Event\TurnStarted;
use App\Game\GameRules;
use App\Game\Model\GameState;

final class TurnTest extends GameTestCase
{
    public function testStartGiveHandsAndFirstTurn(): void
    {
        $state = $this->startGame();

        // Первый ход: игрок 0 взял ещё 1 карту к стартовым 3, второй игрок — 4 (за ход вторым)
        self::assertCount(4, $state->player(0)->hand);
        self::assertCount(4, $state->player(1)->hand);
        self::assertSame(0, $state->activePlayer);
        self::assertSame(1, $state->player(0)->mana);
        self::assertSame(GameRules::FORTIFICATION, $state->player(0)->fortification);
    }

    public function testManaGrowsEachTurnUpToMax(): void
    {
        $state = $this->startGame(deckSize: 30);

        for ($turn = 1; $turn < 20; ++$turn) {
            $this->play(new EndTurn($state->activePlayer));
        }

        self::assertSame(GameRules::MAX_MANA, $state->player(0)->maxMana);
        self::assertSame(GameRules::MAX_MANA, $state->player(0)->mana);
    }

    public function testEndTurnPassesTurnAndDraws(): void
    {
        $state = $this->startGame();

        $events = $this->play(new EndTurn(0));

        self::assertSame(1, $state->activePlayer);
        self::assertSame(1, $state->player(1)->mana);
        self::assertCount(5, $state->player(1)->hand);
        self::assertCount(1, $this->eventsOf($events, TurnStarted::class));
        self::assertCount(1, $this->eventsOf($events, CardDrawn::class));
    }

    public function testCannotActOnOpponentTurn(): void
    {
        $this->startGame();

        $this->expectException(IllegalActionException::class);
        $this->play(new EndTurn(1));
    }

    public function testSameSeedGivesSameGame(): void
    {
        $deck = [];
        foreach (range(1, 10) as $i) {
            $deck[] = $this->creature("Карта $i", 1, 1, 1);
        }

        $buildings = [$this->building('Руины'), $this->building('Развалины')];
        $order = static fn (GameState $s) => array_map(static fn ($c) => $c->definition->name, $s->player(0)->deck);

        self::assertSame($order(GameState::create($deck, $deck, $buildings, 7)), $order(GameState::create($deck, $deck, $buildings, 7)));
        self::assertNotSame($order(GameState::create($deck, $deck, $buildings, 7)), $order(GameState::create($deck, $deck, $buildings, 8)));
    }
}
