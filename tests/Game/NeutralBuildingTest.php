<?php

namespace App\Tests\Game;

use App\Game\Action\CapturePoint;
use App\Game\Action\EndTurn;
use App\Game\Event\CardCreated;
use App\Game\Event\ManaGained;
use App\Game\GameRules;
use App\Game\Model\GameState;

final class NeutralBuildingTest extends GameTestCase
{
    public function testEachGameGetsRandomDistinctBuildingsBySeed(): void
    {
        $pool = array_map(fn (string $name) => $this->building($name), ['Кузница', 'Башня', 'Колодец', 'Мельница', 'Храм']);
        $deck = [$this->creature('Ополченец', 1, 1, 1)];
        $names = static fn (GameState $state) => array_map(static fn ($b) => $b->definition->name, $state->buildings());

        $game = $names(GameState::create($deck, $deck, $pool, 1));
        self::assertCount(GameRules::CAPTURE_POINTS, array_unique($game), 'две разные постройки');
        self::assertSame($game, $names(GameState::create($deck, $deck, $pool, 1)), 'то же зерно — те же постройки');

        $variety = [];
        foreach (range(1, 20) as $seed) {
            $variety[implode('+', $names(GameState::create($deck, $deck, $pool, $seed)))] = true;
        }
        self::assertGreaterThan(3, count($variety), 'от партии к партии постройки меняются');
    }

    public function testPoolMustHaveEnoughBuildings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GameState::create([], [], [$this->building('Одна')], 1);
    }

    public function testForgeCraftsItemsForControllerInTurn(): void
    {
        $state = $this->startGame(buildings: [$this->building('Кузница', ['Forge']), $this->building('Руины')]);
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);
        $this->play(new CapturePoint(0, $scout->id, $this->pointWith('Кузница')));
        $hand = static fn () => array_map(static fn ($card) => $card->definition->name, $state->player(0)->hand);
        $before = count($state->player(0)->hand);

        $this->play(new EndTurn(0));
        self::assertCount(0, array_filter($state->player(1)->hand, static fn ($card) => $card->definition->kind->value === 'item'), 'противнику — ничего');

        $events = $this->play(new EndTurn(1));
        self::assertContains('Кинжал', $hand(), 'первый предмет — Кинжал');
        self::assertCount(1, $this->eventsOf($events, CardCreated::class));

        $this->play(new EndTurn(0));
        $this->play(new EndTurn(1));
        self::assertContains('Щит', $hand(), 'затем — Щит');
        self::assertCount($before + 4, $state->player(0)->hand, '2 добора + 2 предмета');
    }

    public function testFreeForgeCraftsNothing(): void
    {
        $state = $this->startGame(buildings: [$this->building('Кузница', ['Forge']), $this->building('Руины')]);
        $this->play(new EndTurn(0));
        $this->play(new EndTurn(1));

        self::assertSame([], array_filter($state->player(0)->hand, static fn ($card) => $card->definition->kind->value === 'item'));
    }

    public function testWatchtowerHitsEnemyAtControllerTurnStart(): void
    {
        $state = $this->startGame(buildings: [$this->building('Башня', ['Watchtower' => 2]), $this->building('Руины')]);
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);
        $this->play(new CapturePoint(0, $scout->id, $this->pointWith('Башня')));

        $this->play(new EndTurn(0));
        self::assertSame(30, $state->player(1)->fortification, 'ход противника — башня молчит');

        $this->play(new EndTurn(1));
        self::assertSame(28, $state->player(1)->fortification, 'начало нашего хода — 2 урона противнику');
    }

    public function testManaWellGivesExtraMana(): void
    {
        $state = $this->startGame(buildings: [$this->building('Колодец', ['Mana_Well']), $this->building('Руины')]);
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);
        $this->play(new CapturePoint(0, $scout->id, $this->pointWith('Колодец')));
        $this->play(new EndTurn(0));

        $events = $this->play(new EndTurn(1));

        self::assertSame(2, $state->player(0)->maxMana);
        self::assertSame(3, $state->player(0)->mana, '2 обычной + 1 от колодца');
        self::assertCount(1, $this->eventsOf($events, ManaGained::class));
    }

    private function pointWith(string $buildingName): int
    {
        foreach ($this->state->capturePoints as $index => $point) {
            if ($point->building->definition->name === $buildingName) {
                return $index;
            }
        }

        self::fail("Постройки «{$buildingName}» нет на точках");
    }
}
