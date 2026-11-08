<?php

namespace App\Tests\Game;

use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\Fog;
use App\Game\Ability\SunnyDay;
use App\Game\Action\EndTurn;
use App\Game\View\GameView;

final class GameViewTest extends GameTestCase
{
    public function testOpponentHandAndDeckAreHidden(): void
    {
        $state = $this->startGame();
        $view = $this->view()->state($state, 0);

        self::assertCount(count($state->player(0)->hand), $view['players'][0]['hand']);
        self::assertNull($view['players'][1]['hand']);
        self::assertSame(4, $view['players'][1]['handCount']);
        self::assertArrayNotHasKey('deck', $view['players'][1]);
        self::assertSame(count($state->player(1)->deck), $view['players'][1]['deckCount']);
    }

    public function testFogHidesOpponentCreaturesButNotOwn(): void
    {
        $state = $this->startGame();
        $hidden = $this->summon(1, $this->creature('Разведчик', 1, 1, 1), 0);
        $state->player(1)->landscape = $state->newInstance($this->landscape('Туман', 1, ['Fog']), 1);

        self::assertSame(['hidden' => true], $this->view()->state($state, 0)['players'][1]['board'][0]);
        self::assertSame($hidden->id, $this->view()->state($state, 1)['players'][1]['board'][0]['id']);
        self::assertSame(['cover'], $this->view()->state($state, 1)['players'][1]['board'][0]['keywords']);
    }

    public function testCurrentStatsIncludeAuras(): void
    {
        $state = $this->startGame();
        $knight = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $knight->damage = 1;
        $state->player(0)->landscape = $state->newInstance($this->landscape('Солнечный день', 1, ['Sunny_Day']), 0);

        $card = $this->view()->state($state, 0)['players'][0]['board'][0];

        self::assertSame(4, $card['attack'], '3 + Солнечный день');
        self::assertSame(2, $card['health'], '3 − 1 урона');
        self::assertSame([3, 3], [$card['baseAttack'], $card['baseHealth']], 'базовые — как в описании');
    }

    public function testOpponentDrawIsHiddenInEvents(): void
    {
        $this->startGame();
        $events = $this->play(new EndTurn(0));

        $forOpponent = $this->view()->events($events, 0);
        $forDrawer = $this->view()->events($events, 1);
        $drawn = static fn (array $events) => array_values(array_filter($events, static fn (array $e) => $e['type'] === 'CardDrawn'))[0];

        self::assertNull($drawn($forOpponent)['card']);
        self::assertIsInt($drawn($forDrawer)['card']);
        self::assertSame(['type' => 'TurnEnded', 'player' => 0], $forOpponent[0]);
    }

    private function view(): GameView
    {
        return new GameView(new AbilityRegistry([new Fog(), new SunnyDay()]));
    }
}
