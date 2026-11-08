<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\CapturePoint;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Model\GameState;

/**
 * Партия сохраняется в JSON (таблица games) и продолжается с того же места.
 */
final class SerializationTest extends GameTestCase
{
    public function testRoundTripKeepsEverything(): void
    {
        $state = $this->startGame(buildings: [$this->building('Кузница', ['Forge' => 2]), $this->building('Руины')]);
        $knight = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3, ['Shield', 'Frenzy']), 0);
        $scout = $this->summon(0, $this->creature('Разведчик', 1, 1, 2), 1);
        $enemy = $this->summon(1, $this->creature('Враг', 1, 2, 4), 0);
        $this->play(new Attack(0, $knight->id, $enemy->id));   // щит сломан, у врага урон
        $this->play(new CapturePoint(0, $scout->id, 0));
        $fog = $this->giveToHand(0, $this->landscape('Туман', 0, ['Fog', 'Duration' => 1]));
        $this->play(new PlayCard(0, $fog->id));

        $restored = GameState::fromArray(json_decode(json_encode($state->toArray()), true));

        self::assertSame($state->toArray(), $restored->toArray());
        self::assertTrue($restored->player(0)->board[0]->state['shield_broken']);
        self::assertSame('Разведчик', $restored->capturePoints[0]->holder->definition->name);
        self::assertSame('Туман', $restored->player(0)->landscape->definition->name);
    }

    public function testRestoredGameContinuesTheSame(): void
    {
        $original = $this->startGame();
        $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $this->play(new EndTurn(0));

        $restored = GameState::fromArray(json_decode(json_encode($original->toArray()), true));

        // Одни и те же ходы — одинаковый результат: колода, порядок добора, id новых карт
        foreach ([$original, $restored] as $state) {
            $this->state = $state;
            $this->play(new EndTurn(1));
            $this->play(new Attack(0, $state->player(0)->board[0]->id));
            $this->giveToHand(0, $this->creature('Новичок', 0, 1, 1));
        }
        self::assertSame($original->toArray(), $restored->toArray());
        self::assertSame(27, $restored->player(1)->fortification);
    }
}
