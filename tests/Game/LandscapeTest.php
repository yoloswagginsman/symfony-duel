<?php

namespace App\Tests\Game;

use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\DarkRain;
use App\Game\Ability\Fog;
use App\Game\Ability\Forest;
use App\Game\Ability\SunnyDay;
use App\Game\Action\Attack;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Engine\GameContext;
use App\Game\Engine\IllegalActionException;
use App\Game\Enum\Keyword;
use App\Game\Event\LandscapeChanged;
use App\Game\Event\LandscapeEnded;
use App\Game\Model\CardDefinition;
use App\Game\Model\CardInstance;

final class LandscapeTest extends GameTestCase
{
    public function testLandscapeLiesOnOwnHalf(): void
    {
        $state = $this->startGame();
        $sunny = $this->giveToHand(0, $this->sunnyDay());

        $events = $this->play(new PlayCard(0, $sunny->id));

        self::assertSame($sunny, $state->player(0)->landscape);
        self::assertNull($state->player(1)->landscape);
        self::assertSame(0, $state->player(0)->mana);
        self::assertNotContains($sunny, $state->player(0)->hand);
        self::assertNotContains($sunny, $state->player(0)->graveyard, 'ландшафт не уходит в сброс, как заклинание');
        self::assertSame(0, $this->eventsOf($events, LandscapeChanged::class)[0]->side);
    }

    public function testEachHalfHasOwnLandscape(): void
    {
        $state = $this->startGame();
        $sunny = $this->giveToHand(0, $this->sunnyDay());
        $this->play(new PlayCard(0, $sunny->id));
        $this->play(new EndTurn(0));

        $forest = $this->giveToHand(1, $this->landscape('Лес', 1, ['Forest']));
        $this->play(new PlayCard(1, $forest->id));

        self::assertSame($sunny, $state->player(0)->landscape, 'ландшафт противника на нашу половину не влияет');
        self::assertSame($forest, $state->player(1)->landscape);
    }

    public function testNewLandscapeReplacesOldOnSameHalf(): void
    {
        $state = $this->startGame();
        $sunny = $this->place(0, $this->sunnyDay());
        $forest = $this->giveToHand(0, $this->landscape('Лес', 1, ['Forest']));

        $events = $this->play(new PlayCard(0, $forest->id));

        self::assertSame($forest, $state->player(0)->landscape);
        self::assertContains($sunny, $state->player(0)->graveyard);
        self::assertSame($sunny, $this->eventsOf($events, LandscapeChanged::class)[0]->previous);
    }

    public function testHostileLandscapeLiesOnOpponentHalfAndReplacesHisOne(): void
    {
        $state = $this->startGame();
        $enemySunny = $this->place(1, $this->sunnyDay());
        $rain = $this->giveToHand(0, $this->darkRain());

        $this->play(new PlayCard(0, $rain->id));

        self::assertSame($rain, $state->player(1)->landscape);
        self::assertNull($state->player(0)->landscape);
        self::assertContains($enemySunny, $state->player(1)->graveyard, 'вытесненный — в сброс своего хозяина');
    }

    public function testSunnyDayBuffsOnlyCreaturesOnItsHalf(): void
    {
        $this->startGame();
        $own = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $enemy = $this->summon(1, $this->creature('Враг', 1, 2, 2), 0);
        $this->place(0, $this->landscape('Солнечный день', 1, ['Sunny_Day' => 2]));

        self::assertSame(5, $this->context()->attack($own));
        self::assertSame(2, $this->context()->attack($enemy));
    }

    public function testLandscapeDoesNotReachCapturePoints(): void
    {
        $state = $this->startGame();
        $holder = $this->summon(0, $this->creature('Разведчик', 1, 2, 2), 0);
        $state->removeFromPlay($holder);
        $state->capturePoints[0]->holder = $holder;
        $this->place(0, $this->sunnyDay());

        self::assertSame(2, $this->context()->attack($holder), 'точки ничьи — не часть половины поля');
    }

    public function testForestBuffsOnlyForestRacesOnItsHalf(): void
    {
        $this->startGame();
        $elf = $this->summon(0, $this->creature('Эльф', 1, 2, 2, race: 'elves'), 0);
        $orc = $this->summon(0, $this->creature('Орк', 1, 2, 2, race: 'orcs'), 1);
        $enemyElf = $this->summon(1, $this->creature('Эльф', 1, 2, 2, race: 'elves'), 0);
        $this->place(0, $this->landscape('Лес', 1, ['Forest']));

        self::assertSame(3, $this->context()->health($elf));
        self::assertSame(2, $this->context()->health($orc));
        self::assertSame(2, $this->context()->health($enemyElf), 'чужая половина — без бонуса');
    }

    public function testDarkRainWeakensCreaturesOnItsHalf(): void
    {
        $this->startGame();
        $own = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $enemy = $this->summon(1, $this->creature('Враг', 1, 3, 3), 0);
        $rain = $this->giveToHand(0, $this->darkRain());
        $this->play(new PlayCard(0, $rain->id));

        self::assertSame(3, $this->context()->attack($own));
        self::assertSame(2, $this->context()->attack($enemy));
    }

    public function testDurationEndsLandscapeAtOwnersTurnStart(): void
    {
        $state = $this->startGame();
        $sunny = $this->giveToHand(0, $this->landscape('Солнечный день', 1, ['Sunny_Day', 'Duration' => 2]));
        $this->play(new PlayCard(0, $sunny->id));

        $this->play(new EndTurn(0));
        $this->play(new EndTurn(1));
        self::assertSame($sunny, $state->player(0)->landscape, 'начало 1-го нашего хода — ещё держится');

        $this->play(new EndTurn(0));
        $events = $this->play(new EndTurn(1));
        self::assertNull($state->player(0)->landscape, 'начало 2-го — истёк');
        self::assertContains($sunny, $state->player(0)->graveyard);
        self::assertCount(1, $this->eventsOf($events, LandscapeEnded::class));
    }

    public function testDurationOfHostileLandscapeCountsCasterTurns(): void
    {
        $state = $this->startGame();
        $rain = $this->giveToHand(0, $this->landscape('Тёмный дождь', 1, ['Hostile', 'Dark_Rain', 'Duration' => 1]));
        $this->play(new PlayCard(0, $rain->id));

        $this->play(new EndTurn(0));
        self::assertSame($rain, $state->player(1)->landscape, 'ход противника — дождь идёт');

        $this->play(new EndTurn(1));
        self::assertNull($state->player(1)->landscape);
        self::assertContains($rain, $state->player(0)->graveyard, 'в сброс разыгравшего');
    }

    public function testDispelWithoutTargetClearsOwnHalf(): void
    {
        $state = $this->startGame();
        $rain = $this->place(0, $this->darkRain(), owner: 1);
        $enemySunny = $this->place(1, $this->sunnyDay());
        $dispel = $this->giveToHand(0, $this->spell('Развеять', 1, ['Dispel']));

        $events = $this->play(new PlayCard(0, $dispel->id));

        self::assertNull($state->player(0)->landscape, 'враждебный дождь снят');
        self::assertSame($enemySunny, $state->player(1)->landscape, 'чужая половина не тронута');
        self::assertContains($rain, $state->player(1)->graveyard);
        self::assertSame($dispel, $this->eventsOf($events, LandscapeEnded::class)[0]->source);
    }

    public function testDispelTargetsOpponentLandscape(): void
    {
        $state = $this->startGame();
        $own = $this->place(0, $this->sunnyDay());
        $enemySunny = $this->place(1, $this->sunnyDay());
        $dispel = $this->giveToHand(0, $this->spell('Развеять', 1, ['Dispel']));

        $this->play(new PlayCard(0, $dispel->id, targetId: $enemySunny->id));

        self::assertNull($state->player(1)->landscape);
        self::assertSame($own, $state->player(0)->landscape);
    }

    public function testFogCoversCreaturesForOpponentTurn(): void
    {
        $state = $this->startGame();
        $hidden = $this->summon(0, $this->creature('Разведчик', 1, 1, 1), 0);
        $attacker = $this->summon(1, $this->creature('Враг', 1, 2, 2), 0);
        $fog = $this->giveToHand(0, $this->fog());
        $this->play(new PlayCard(0, $fog->id));
        $this->play(new EndTurn(0));

        try {
            $this->play(new Attack(1, $attacker->id, $hidden->id));
            self::fail('существо в тумане атаковать нельзя');
        } catch (IllegalActionException) {
        }
        $this->play(new Attack(1, $attacker->id));
        self::assertSame(28, $state->player(0)->fortification, 'фортификация туманом не прикрыта');

        $this->play(new EndTurn(1));
        self::assertNull($state->player(0)->landscape, 'туман на 1 ход — рассеялся в начале нашего хода');
    }

    public function testFogCannotHideTaunt(): void
    {
        $state = $this->startGame();
        $guard = $this->summon(1, $this->creature('Страж', 1, 1, 5, ['Taunt']), 0);
        $this->summon(1, $this->creature('Враг', 1, 2, 2), 1);
        $own = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $this->place(1, $this->fog());

        try {
            $this->play(new Attack(0, $own->id));
            self::fail('Страж в тумане на виду — Провокация действует');
        } catch (IllegalActionException) {
        }

        $this->play(new Attack(0, $own->id, $guard->id));
        self::assertSame(3, $guard->damage);
    }

    public function testFogCoversOnlyItsHalf(): void
    {
        $state = $this->startGame();
        $enemy = $this->summon(1, $this->creature('Враг', 1, 2, 2), 0);
        $own = $this->summon(0, $this->creature('Рыцарь', 1, 3, 3), 0);
        $this->place(1, $this->fog());
        $context = new GameContext($state, new AbilityRegistry([new Fog()]));

        self::assertTrue($context->hasKeyword($enemy, Keyword::Cover));
        self::assertFalse($context->hasKeyword($own, Keyword::Cover), 'туман противника нас не прячет');
    }

    private function fog(): CardDefinition
    {
        return $this->landscape('Туман', 1, ['Fog', 'Duration' => 1]);
    }

    private function sunnyDay(): CardDefinition
    {
        return $this->landscape('Солнечный день', 1, ['Sunny_Day']);
    }

    private function darkRain(): CardDefinition
    {
        return $this->landscape('Тёмный дождь', 1, ['Hostile', 'Dark_Rain']);
    }

    /**
     * Положить ландшафт на половину $side напрямую (без правил). Хозяин по умолчанию — тот же игрок.
     */
    private function place(int $side, CardDefinition $definition, ?int $owner = null): CardInstance
    {
        $landscape = $this->state->newInstance($definition, $owner ?? $side);
        $this->state->player($side)->landscape = $landscape;

        return $landscape;
    }

    private function context(): GameContext
    {
        return new GameContext($this->state, new AbilityRegistry([new SunnyDay(), new Forest(), new DarkRain()]));
    }
}
