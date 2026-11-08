<?php

namespace App\Game\Model;

use App\Game\GameRules;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Состояние партии. Меняет его только GameEngine (через GameContext).
 */
final class GameState
{
    public int $activePlayer = 0;
    public int $turn = 0;
    public ?int $winner = null;

    /** @var list<CapturePoint> общие точки сопряжения */
    public array $capturePoints = [];

    /**
     * Карты, которые создаются по ходу партии (предметы Кузницы), — по vendorCode.
     *
     * @var array<string, CardDefinition>
     */
    public array $tokens = [];

    private int $nextInstanceId = 1;

    /**
     * @param array{0: PlayerState, 1: PlayerState} $players
     */
    private function __construct(
        public array $players,
    ) {
    }

    /**
     * Новая партия. По зерну перемешиваются колоды и выбираются нейтральные постройки
     * на точки сопряжения — одна и та же партия воспроизводится.
     *
     * @param list<CardDefinition> $deck0
     * @param list<CardDefinition> $deck1
     * @param list<CardDefinition> $neutralBuildings пул нейтральных построек, из него берутся разные
     * @param list<CardDefinition> $tokens           карты, которые могут появиться по ходу партии (предметы)
     */
    public static function create(array $deck0, array $deck1, array $neutralBuildings, int $seed, array $tokens = []): self
    {
        if (count($neutralBuildings) < GameRules::CAPTURE_POINTS) {
            throw new \InvalidArgumentException(sprintf('Нужно не меньше %d нейтральных построек.', GameRules::CAPTURE_POINTS));
        }

        $state = new self([new PlayerState(0, []), new PlayerState(1, [])]);
        $randomizer = new Randomizer(new Mt19937($seed));

        foreach ([$deck0, $deck1] as $owner => $deck) {
            $cards = array_map(fn (CardDefinition $definition) => $state->newInstance($definition, $owner), $deck);
            $state->players[$owner]->deck = $randomizer->shuffleArray($cards);
        }

        foreach ($randomizer->pickArrayKeys($neutralBuildings, GameRules::CAPTURE_POINTS) as $key) {
            $state->capturePoints[] = new CapturePoint($state->newInstance($neutralBuildings[$key], GameRules::NEUTRAL));
        }

        foreach ($tokens as $token) {
            $state->tokens[$token->vendorCode] = $token;
        }

        return $state;
    }

    /**
     * Состояние для хранения (JSON в таблице games). Каждая карта лежит ровно в одном месте —
     * в руке, колоде, на поле, на точке, в сбросе или в слоте ландшафта, — поэтому вложенный вид без ссылок.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'activePlayer' => $this->activePlayer,
            'turn' => $this->turn,
            'winner' => $this->winner,
            'nextInstanceId' => $this->nextInstanceId,
            'capturePoints' => array_map(static fn (CapturePoint $point) => $point->toArray(), $this->capturePoints),
            'players' => array_map(static fn (PlayerState $player) => $player->toArray(), $this->players),
            'tokens' => array_map(static fn (CardDefinition $token) => $token->toArray(), $this->tokens),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $state = new self([PlayerState::fromArray($data['players'][0]), PlayerState::fromArray($data['players'][1])]);
        $state->activePlayer = $data['activePlayer'];
        $state->turn = $data['turn'];
        $state->winner = $data['winner'];
        $state->nextInstanceId = $data['nextInstanceId'];
        $state->capturePoints = array_map(CapturePoint::fromArray(...), $data['capturePoints']);
        // Партии, начатые до появления предметов, хранятся без tokens
        $state->tokens = array_map(CardDefinition::fromArray(...), $data['tokens'] ?? []);

        return $state;
    }

    public function newInstance(CardDefinition $definition, int $owner): CardInstance
    {
        return new CardInstance($this->nextInstanceId++, $definition, $owner);
    }

    public function player(int $index): PlayerState
    {
        return $this->players[$index];
    }

    public function opponentOf(int $index): int
    {
        return 1 - $index;
    }

    public function isOver(): bool
    {
        return $this->winner !== null;
    }

    /**
     * Существа в игре: на полях обоих игроков и на точках сопряжения (постройки сюда не входят).
     *
     * @return list<CardInstance>
     */
    public function cardsInPlay(?int $owner = null): array
    {
        $holders = array_map(static fn (CapturePoint $point) => $point->holder, $this->capturePoints);
        $cards = [...$this->players[0]->board, ...$this->players[1]->board, ...$holders];

        return array_values(array_filter(
            $cards,
            static fn (?CardInstance $card) => $card !== null && ($owner === null || $card->owner === $owner),
        ));
    }

    public function findInPlay(int $id): ?CardInstance
    {
        foreach ($this->cardsInPlay() as $card) {
            if ($card->id === $id) {
                return $card;
            }
        }

        return null;
    }

    /**
     * @return list<CardInstance> нейтральные постройки на точках
     */
    public function buildings(): array
    {
        return array_map(static fn (CapturePoint $point) => $point->building, $this->capturePoints);
    }

    /**
     * Кто владеет постройкой на точке (держит точку), null — точка свободна.
     */
    public function controllerOf(CardInstance $building): ?int
    {
        foreach ($this->capturePoints as $point) {
            if ($point->building === $building) {
                return $point->controller();
            }
        }

        return null;
    }

    /**
     * Карты с постоянным эффектом, которые не существа: постройки на точках и ландшафты обеих половин.
     *
     * @return list<CardInstance>
     */
    public function permanents(): array
    {
        $landscapes = array_filter([$this->players[0]->landscape, $this->players[1]->landscape]);

        return [...$this->buildings(), ...$landscapes];
    }

    public function findLandscape(int $id): ?CardInstance
    {
        foreach ($this->players as $player) {
            if ($player->landscape?->id === $id) {
                return $player->landscape;
            }
        }

        return null;
    }

    /**
     * На чьей половине поля лежит ландшафт; null — ландшафта уже нет в игре.
     */
    public function sideOf(CardInstance $landscape): ?int
    {
        foreach ($this->players as $player) {
            if ($player->landscape === $landscape) {
                return $player->index;
            }
        }

        return null;
    }

    /**
     * Существо стоит на точке сопряжения (держит её).
     */
    public function holdsPoint(CardInstance $card): bool
    {
        foreach ($this->capturePoints as $point) {
            if ($point->holder === $card) {
                return true;
            }
        }

        return false;
    }

    public function removeFromPlay(CardInstance $card): void
    {
        $board = &$this->players[$card->owner]->board;
        foreach ($board as $cell => $occupant) {
            if ($occupant === $card) {
                $board[$cell] = null;
            }
        }
        foreach ($this->capturePoints as $point) {
            if ($point->holder === $card) {
                $point->holder = null;
            }
        }
    }
}
