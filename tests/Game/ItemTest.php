<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\PlayCard;
use App\Game\Engine\GameContext;
use App\Game\Engine\IllegalActionException;
use App\Game\Event\CreatureBuffed;
use App\Game\Model\GameState;

/**
 * Предметы (артефакты): надеваются на своё существо и усиливают его, пока оно живо.
 */
final class ItemTest extends GameTestCase
{
    public function testDaggerAddsAttackForever(): void
    {
        $state = $this->startGame();
        $knight = $this->summon(0, $this->creature('Рыцарь', 1, 2, 3), 0);
        $dagger = $this->giveToHand(0, $this->items()[0]);

        $events = $this->play(new PlayCard(0, $dagger->id, targetId: $knight->id));

        self::assertCount(1, $this->eventsOf($events, CreatureBuffed::class));
        self::assertSame([['name' => 'Кинжал', 'attack' => 1, 'health' => 0]], $knight->state['equipment'], 'видно, что надето');
        self::assertContains($dagger, $state->player(0)->graveyard, 'предмет израсходован');

        $enemy = $this->summon(1, $this->creature('Враг', 1, 0, 5), 0);
        $this->play(new Attack(0, $knight->id, $enemy->id));
        self::assertSame(3, $enemy->damage, '2 + 1 от Кинжала');
    }

    public function testShieldAddsHealth(): void
    {
        $this->startGame();
        $knight = $this->summon(0, $this->creature('Рыцарь', 1, 2, 3), 0);
        $shield = $this->giveToHand(0, $this->items()[1]);

        $this->play(new PlayCard(0, $shield->id, targetId: $knight->id));
        $knight->damage = 3;

        self::assertSame(1, (new GameContext($this->state, $this->registry()))->health($knight), '3 + 1 − 3');
    }

    public function testItemOnlyOnOwnCreature(): void
    {
        $this->startGame();
        $enemy = $this->summon(1, $this->creature('Враг', 1, 2, 2), 0);
        $dagger = $this->giveToHand(0, $this->items()[0]);

        try {
            $this->play(new PlayCard(0, $dagger->id, targetId: $enemy->id));
            self::fail('на чужое существо нельзя');
        } catch (IllegalActionException $exception) {
            self::assertSame('Предмет надевается на своё существо — выберите его.', $exception->getMessage());
        }

        $this->expectException(IllegalActionException::class);
        $this->play(new PlayCard(0, $dagger->id));
    }

    public function testBuffSurvivesSaving(): void
    {
        $state = $this->startGame();
        $knight = $this->summon(0, $this->creature('Рыцарь', 1, 2, 3), 0);
        $this->play(new PlayCard(0, $this->giveToHand(0, $this->items()[0])->id, targetId: $knight->id));

        $restored = GameState::fromArray(json_decode(json_encode($state->toArray()), true));

        self::assertSame(1, $restored->player(0)->board[0]->state['attack_bonus']);
        self::assertSame(['DUEL-ITE-NEU-KINZAL', 'DUEL-ITE-NEU-SIT'], array_keys($restored->tokens));
    }

    public function testGameSavedBeforeItemsStillLoads(): void
    {
        $data = $this->startGame()->toArray();
        unset($data['tokens']);

        self::assertSame([], GameState::fromArray($data)->tokens);
    }
}
