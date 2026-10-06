<?php

namespace App\Tests\Game;

use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\Damage;
use App\Game\Action\PlayCard;
use App\Game\Engine\GameContext;
use App\Game\Event\CreatureDamaged;
use App\Game\Event\CreatureSummoned;

/**
 * Призыв: разовые эффекты существа срабатывают, когда оно выходит на поле.
 */
final class SummonTest extends GameTestCase
{
    public function testSummonDamagesChosenTarget(): void
    {
        $state = $this->startGame();
        $enemy = $this->summon(1, $this->creature('Враг', 1, 1, 3), 0);
        $archer = $this->giveToHand(0, $this->creature('Лучник', 1, 1, 1, ['Damage' => 2]));

        $events = $this->play(new PlayCard(0, $archer->id, cell: 0, targetId: $enemy->id));

        self::assertSame(2, $enemy->damage);
        self::assertSame($archer, $state->player(0)->board[0]);
        self::assertSame($archer, $this->eventsOf($events, CreatureDamaged::class)[0]->source);
    }

    public function testSummonWithoutTargetHitsFortification(): void
    {
        $state = $this->startGame();
        $archer = $this->giveToHand(0, $this->creature('Лучник', 1, 1, 1, ['Damage' => 2]));

        $this->play(new PlayCard(0, $archer->id, cell: 0));

        self::assertSame(28, $state->player(1)->fortification);
    }

    public function testSummonTriggersOnlyOnce(): void
    {
        $state = $this->startGame();
        $archer = $this->giveToHand(0, $this->creature('Лучник', 1, 1, 1, ['Damage']));
        $this->play(new PlayCard(0, $archer->id, cell: 0));

        // Другие события (ещё одно существо вышло) Призыв лучника не повторяют
        $other = $this->giveToHand(0, $this->creature('Ополченец', 0, 1, 1));
        $this->play(new PlayCard(0, $other->id, cell: 1));

        self::assertSame(29, $state->player(1)->fortification);
    }

    public function testCreatureSummonedByAnotherCardAlsoTriggers(): void
    {
        $state = $this->startGame();
        $token = $state->newInstance($this->creature('Волк', 0, 1, 1, ['Damage']), 0);

        $game = new GameContext($state, new AbilityRegistry([new Damage()]));
        $game->summon($token, 3);
        $game->resolve();

        self::assertSame($token, $state->player(0)->board[3]);
        self::assertSame(29, $state->player(1)->fortification);
        self::assertCount(1, array_filter($game->log(), static fn ($e) => $e instanceof CreatureSummoned));
    }

    public function testPoisonousSummonKills(): void
    {
        $state = $this->startGame();
        $enemy = $this->summon(1, $this->creature('Огр', 1, 5, 9), 0);
        $assassin = $this->giveToHand(0, $this->creature('Ассасин', 1, 1, 1, ['Damage', 'Poison']));

        $this->play(new PlayCard(0, $assassin->id, cell: 0, targetId: $enemy->id));

        self::assertNull($state->player(1)->board[0], 'Призыв ядовитого существа — смертельный');
    }

    public function testSpellEffectFiresOnceOnPlay(): void
    {
        $state = $this->startGame();
        $state->player(0)->fortification = 20;
        $spell = $this->giveToHand(0, $this->spell('Починка', 1, ['Repair' => 3]));

        $this->play(new PlayCard(0, $spell->id));

        self::assertSame(23, $state->player(0)->fortification, 'заклинание — при розыгрыше, ровно один раз');
    }
}
