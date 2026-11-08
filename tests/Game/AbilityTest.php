<?php

namespace App\Tests\Game;

use App\Game\Action\Attack;
use App\Game\Action\PlayCard;
use App\Game\Engine\GameContext;
use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\Frenzy;
use App\Game\Ability\PackLeader;
use App\Game\Event\CreatureHealed;
use App\Game\Event\FortificationRepaired;

final class AbilityTest extends GameTestCase
{
    public function testTauntForcesTarget(): void
    {
        $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);
        $guard = $this->summon(1, $this->creature('Страж', 1, 1, 5, ['Taunt']), 0);

        try {
            $this->play(new Attack(0, $wolf->id));
            self::fail('Атака мимо Провокации должна быть запрещена');
        } catch (\DomainException $e) {
            self::assertStringContainsString('Провокация', $e->getMessage());
        }

        $this->play(new Attack(0, $wolf->id, $guard->id));
        self::assertSame(3, $guard->damage);
    }

    public function testChargeAttacksOnSummonTurn(): void
    {
        $state = $this->startGame();
        $rider = $this->giveToHand(0, $this->creature('Всадник', 1, 2, 1, ['Charge']));
        $this->play(new PlayCard(0, $rider->id, cell: 0));

        $this->play(new Attack(0, $rider->id));

        self::assertSame(28, $state->player(1)->fortification);
    }

    public function testFlyingCanBeAttackedOnlyByFlying(): void
    {
        $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 3), 0);
        $eagle = $this->summon(0, $this->creature('Орёл', 1, 2, 2, ['Flying']), 1);
        $dragon = $this->summon(1, $this->creature('Дракон', 1, 1, 5, ['Flying']), 0);

        try {
            $this->play(new Attack(0, $wolf->id, $dragon->id));
            self::fail('Нелетающий не должен атаковать летающего');
        } catch (\DomainException) {
        }

        $this->play(new Attack(0, $eagle->id, $dragon->id));
        self::assertSame(2, $dragon->damage);
    }

    public function testShieldAbsorbsFirstDamage(): void
    {
        $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 3, 9), 0);
        $knight = $this->summon(1, $this->creature('Рыцарь', 1, 1, 4, ['Shield']), 0);

        $this->play(new Attack(0, $wolf->id, $knight->id));
        self::assertSame(0, $knight->damage, 'первый удар — в щит');

        $wolf->actedThisTurn = false;
        $this->play(new Attack(0, $wolf->id, $knight->id));
        self::assertSame(3, $knight->damage, 'щит сломан');
    }

    public function testRepairRestoresFortificationOnPlay(): void
    {
        $state = $this->startGame();
        $state->player(0)->fortification = 20;
        $mason = $this->giveToHand(0, $this->creature('Каменщик', 1, 1, 1, ['Repair' => 3]));

        $events = $this->play(new PlayCard(0, $mason->id, cell: 0));

        self::assertSame(23, $state->player(0)->fortification);
        self::assertCount(1, $this->eventsOf($events, FortificationRepaired::class));
    }

    public function testRepairDoesNotExceedMaximum(): void
    {
        $state = $this->startGame();
        $mason = $this->giveToHand(0, $this->creature('Каменщик', 1, 1, 1, ['Repair']));

        $this->play(new PlayCard(0, $mason->id, cell: 0));

        self::assertSame(30, $state->player(0)->fortification);
    }

    public function testHealRestoresOwnCreaturesNotFortification(): void
    {
        $state = $this->startGame();
        $state->player(0)->fortification = 20;
        $wounded = $this->summon(0, $this->creature('Раненый', 1, 1, 5), 0);
        $wounded->damage = 3;
        $scratched = $this->summon(0, $this->creature('Оцарапанный', 1, 1, 5), 1);
        $scratched->damage = 1;
        $enemy = $this->summon(1, $this->creature('Враг', 1, 1, 5), 0);
        $enemy->damage = 3;
        $priest = $this->giveToHand(0, $this->creature('Жрец', 1, 1, 1, ['Heal' => 2]));

        $events = $this->play(new PlayCard(0, $priest->id, cell: 2));

        self::assertSame(1, $wounded->damage, '3 - 2');
        self::assertSame(0, $scratched->damage, 'выше максимума не лечит');
        self::assertSame(3, $enemy->damage, 'врагов не лечит');
        self::assertSame(20, $state->player(0)->fortification, 'фортификацию не трогает — это Repair');
        self::assertCount(2, $this->eventsOf($events, CreatureHealed::class));
    }

    public function testAoeSpellHitsAllEnemies(): void
    {
        $state = $this->startGame();
        $a = $this->summon(1, $this->creature('Гоблин', 1, 1, 1), 0);
        $b = $this->summon(1, $this->creature('Тролль', 1, 1, 4), 1);
        $own = $this->summon(0, $this->creature('Свой', 1, 1, 1), 0);
        $storm = $this->giveToHand(0, $this->spell('Буря', 1, ['AoE_Damage' => 2]));

        $this->play(new PlayCard(0, $storm->id));

        self::assertNull($state->player(1)->board[0], 'гоблин погиб');
        self::assertSame(2, $b->damage);
        self::assertSame(0, $own->damage, 'свои не задеты');
        self::assertContains($storm, $state->player(0)->graveyard);
    }

    public function testFrenzyGrowsAttackWhenDamaged(): void
    {
        $this->startGame();
        $wolf = $this->summon(0, $this->creature('Волк', 1, 1, 9), 0);
        $berserk = $this->summon(1, $this->creature('Берсерк', 1, 2, 5, ['Frenzy' => 2]), 0);

        $this->play(new Attack(0, $wolf->id, $berserk->id));

        self::assertSame(4, $this->context()->attack($berserk), '2 + 2 за полученный урон');
    }

    public function testPackLeaderDependsOnBoard(): void
    {
        $this->startGame();
        $leader = $this->summon(0, $this->creature('Вожак', 1, 2, 3, ['Pack_Leader'], race: 'orcs'), 0);
        self::assertSame(2, $this->context()->attack($leader), 'стаи нет');

        $this->summon(0, $this->creature('Орк', 1, 1, 1, race: 'orcs'), 1);
        $orc2 = $this->summon(0, $this->creature('Орк', 1, 1, 1, race: 'orcs'), 2);
        $this->summon(0, $this->creature('Эльф', 1, 1, 1, race: 'elves'), 3);
        self::assertSame(4, $this->context()->attack($leader), '+1 за каждого из 2 орков, эльф не считается');

        $this->state->removeFromPlay($orc2);
        self::assertSame(3, $this->context()->attack($leader), 'орк ушёл — бонус пересчитался');
    }

    public function testPoisonKillsOnAnyDamage(): void
    {
        $state = $this->startGame();
        $assassin = $this->summon(0, $this->creature('Ассасин', 1, 1, 1, ['Poison']), 0);
        $giant = $this->summon(1, $this->creature('Великан', 1, 9, 9), 0);

        $this->play(new Attack(0, $assassin->id, $giant->id));

        self::assertNull($state->player(1)->board[0], '1 урона с ядом — великан погиб');
        self::assertNull($state->player(0)->board[0], 'ассасин тоже погиб от ответного удара');
    }

    public function testShieldBlocksPoison(): void
    {
        $state = $this->startGame();
        $assassin = $this->summon(0, $this->creature('Ассасин', 1, 1, 3, ['Poison']), 0);
        $knight = $this->summon(1, $this->creature('Рыцарь', 1, 1, 5, ['Shield']), 0);

        $this->play(new Attack(0, $assassin->id, $knight->id));

        self::assertSame($knight, $state->player(1)->board[0], 'щит поглотил удар — урона не было, яд не сработал');
    }

    public function testPoisonedAoeSpellKillsEveryDamagedEnemy(): void
    {
        $state = $this->startGame();
        $this->summon(1, $this->creature('Тролль', 1, 1, 8), 0);
        $knight = $this->summon(1, $this->creature('Рыцарь', 1, 1, 8, ['Shield']), 1);
        $curse = $this->giveToHand(0, $this->spell('Проклятие', 1, ['Poison', 'AoE_Damage']));

        $this->play(new PlayCard(0, $curse->id));

        self::assertNull($state->player(1)->board[0], 'тролль погиб от ядовитого урона');
        self::assertSame($knight, $state->player(1)->board[1], 'рыцаря спас щит');
    }

    public function testUnknownAbilityIsIgnored(): void
    {
        $state = $this->startGame();
        $mystery = $this->giveToHand(0, $this->creature('Загадка', 1, 1, 1, ['Not_Implemented_Yet']));

        $this->play(new PlayCard(0, $mystery->id, cell: 0));

        self::assertSame($mystery, $state->player(0)->board[0]);
    }

    private function context(): GameContext
    {
        return new GameContext($this->state, new AbilityRegistry([new Frenzy(), new PackLeader()]));
    }
}
