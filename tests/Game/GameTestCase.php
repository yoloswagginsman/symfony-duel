<?php

namespace App\Tests\Game;

use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\AoeDamage;
use App\Game\Ability\Charge;
use App\Game\Ability\Damage;
use App\Game\Ability\DarkRain;
use App\Game\Ability\EquipAttack;
use App\Game\Ability\EquipHealth;
use App\Game\Ability\Dispel;
use App\Game\Ability\Duration;
use App\Game\Ability\Flying;
use App\Game\Ability\Fog;
use App\Game\Ability\Frenzy;
use App\Game\Ability\Forest;
use App\Game\Ability\Forge;
use App\Game\Ability\Heal;
use App\Game\Ability\Hostile;
use App\Game\Ability\ManaWell;
use App\Game\Ability\PackLeader;
use App\Game\Ability\Poison;
use App\Game\Ability\Repair;
use App\Game\Ability\Shield;
use App\Game\Ability\SunnyDay;
use App\Game\Ability\Taunt;
use App\Game\Ability\Watchtower;
use App\Game\Action\ActionInterface;
use App\Game\Action\StartGame;
use App\Game\Engine\GameEngine;
use App\Game\Event\GameEvent;
use App\Game\Model\AbilityRef;
use App\Game\Model\CardDefinition;
use App\Game\Model\CardInstance;
use App\Game\Model\GameState;
use App\Game\Enum\CardKind;
use PHPUnit\Framework\TestCase;

/**
 * Помощники для тестов движка: партия собирается в памяти, без базы и контейнера.
 */
abstract class GameTestCase extends TestCase
{
    protected GameEngine $engine;
    protected GameState $state;

    protected function setUp(): void
    {
        $this->engine = new GameEngine($this->registry());
    }

    /**
     * Все способности — как в контейнере.
     */
    protected function registry(): AbilityRegistry
    {
        return new AbilityRegistry([
            new Taunt(), new Charge(), new Flying(), new Shield(),
            new Heal(), new Repair(), new Damage(), new AoeDamage(), new Frenzy(), new PackLeader(), new Poison(),
            new Forge(), new Watchtower(), new ManaWell(),
            new SunnyDay(), new Forest(), new DarkRain(), new Hostile(), new Fog(), new Duration(), new Dispel(),
            new EquipAttack(), new EquipHealth(),
        ]);
    }

    /**
     * Начатая партия: колоды из простых существ (1 мана, 1/1), ходит игрок 0.
     * На точках — постройки из $buildings (по умолчанию — две без бонусов).
     *
     * @param list<CardDefinition>|null $buildings
     */
    protected function startGame(int $deckSize = 10, ?array $buildings = null): GameState
    {
        $deck = array_fill(0, $deckSize, $this->creature('Ополченец', 1, 1, 1));
        $buildings ??= [$this->building('Руины'), $this->building('Развалины')];
        $this->state = GameState::create($deck, $deck, $buildings, seed: 42, tokens: $this->items());
        $this->play(new StartGame());

        return $this->state;
    }

    /**
     * @return list<GameEvent>
     */
    protected function play(ActionInterface $action): array
    {
        return $this->engine->apply($this->state, $action);
    }

    /**
     * @param array<int|string, string|int> $abilities ['Taunt', 'Heal' => 3]
     */
    protected function creature(string $name, int $mana, int $attack, int $health, array $abilities = [], ?string $race = null): CardDefinition
    {
        return new CardDefinition($name, $name, CardKind::Creature, $mana, $attack, $health, $race, $this->abilityRefs($abilities));
    }

    /**
     * @param array<int|string, string|int> $abilities
     */
    protected function spell(string $name, int $mana, array $abilities): CardDefinition
    {
        return new CardDefinition($name, $name, CardKind::Spell, $mana, abilities: $this->abilityRefs($abilities));
    }

    /**
     * @param array<int|string, string|int> $abilities
     */
    protected function building(string $name, array $abilities = []): CardDefinition
    {
        return new CardDefinition($name, $name, CardKind::Building, 0, abilities: $this->abilityRefs($abilities));
    }

    /**
     * @param array<int|string, string|int> $abilities
     */
    protected function landscape(string $name, int $mana, array $abilities): CardDefinition
    {
        return new CardDefinition($name, $name, CardKind::Landscape, $mana, abilities: $this->abilityRefs($abilities));
    }

    /**
     * Предметы Кузницы — с теми же vendorCode, что в контенте.
     *
     * @return list<CardDefinition>
     */
    protected function items(): array
    {
        return [
            new CardDefinition('DUEL-ITE-NEU-KINZAL', 'Кинжал', CardKind::Item, 1, abilities: [new AbilityRef('Equip_Attack', 1)]),
            new CardDefinition('DUEL-ITE-NEU-SIT', 'Щит', CardKind::Item, 1, abilities: [new AbilityRef('Equip_Health', 1)]),
        ];
    }

    /**
     * Положить существо прямо на поле (для подготовки ситуации, без правил).
     * $ready — может действовать в этот ход.
     */
    protected function summon(int $player, CardDefinition $definition, int $cell, bool $ready = true): CardInstance
    {
        $card = $this->state->newInstance($definition, $player);
        $card->summonedThisTurn = !$ready;
        $this->state->player($player)->board[$cell] = $card;

        return $card;
    }

    protected function giveToHand(int $player, CardDefinition $definition): CardInstance
    {
        $card = $this->state->newInstance($definition, $player);
        $this->state->player($player)->hand[] = $card;

        return $card;
    }

    /**
     * @param list<GameEvent> $events
     * @param class-string<GameEvent> $class
     *
     * @return list<GameEvent>
     */
    protected function eventsOf(array $events, string $class): array
    {
        return array_values(array_filter($events, static fn (GameEvent $e) => $e instanceof $class));
    }

    /**
     * @param array<int|string, string|int> $abilities
     *
     * @return list<AbilityRef>
     */
    private function abilityRefs(array $abilities): array
    {
        $refs = [];
        foreach ($abilities as $key => $value) {
            $refs[] = is_int($key) ? new AbilityRef($value) : new AbilityRef($key, $value);
        }

        return $refs;
    }
}
