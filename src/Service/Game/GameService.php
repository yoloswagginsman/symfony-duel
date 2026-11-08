<?php

namespace App\Service\Game;

use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\GameMove;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Game\Action\ActionInterface;
use App\Game\Action\EndTurn;
use App\Game\Action\StartGame;
use App\Game\Ai\ComputerPlayer;
use App\Game\Engine\GameEngine;
use App\Game\Engine\IllegalActionException;
use App\Game\Engine\Step;
use App\Game\Enum\CardKind;
use App\Game\Event\GameEvent;
use App\Game\Factory\CardDefinitionFactory;
use App\Game\GameRules;
use App\Game\Model\CardDefinition;
use App\Game\Model\GameState;
use App\Game\View\GameView;
use App\Repository\CardRepository;
use App\Repository\DeckRepository;
use App\Service\Game\Output\GameStarted;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Партии в базе: создать, присоединиться (старт), сыграть с компьютером, сделать ход.
 * Правила — в движке (GameEngine), здесь — кто играет, чем и сохранение состояния.
 */
readonly class GameService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameEngine $engine,
        private CardDefinitionFactory $cardDefinitionFactory,
        private CardRepository $cardRepository,
        private DeckRepository $deckRepository,
        private ComputerPlayer $computerPlayer,
        private ClockInterface $clock,
        private GameView $gameView,
        private int $turnSeconds,
    ) {
    }

    /**
     * Новая партия ждёт второго игрока.
     */
    public function create(User $creator, Deck $deck): Game
    {
        $this->checkDeck($creator, $deck);

        $game = new Game($creator, $deck);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    /**
     * Второй игрок присоединяется — партия начинается. Кто ходит первым, решает жребий (по зерну партии).
     *
     * @return list<Step> старт: стартовые руки, первый ход (и ход компьютера, если он первый)
     */
    public function join(Game $game, User $user, Deck $deck): array
    {
        if ($game->getStatus() !== GameStatus::WAITING) {
            throw new GameException('Партия уже началась.');
        }
        $creator = $game->getPlayer0();
        if ($creator === $user) {
            throw new GameException('Нельзя присоединиться к своей партии.');
        }
        $creatorDeck = $game->getCreatorDeck() ?? throw new GameException('Колода создателя партии удалена.');
        $this->checkDeck($user, $deck);

        $steps = $this->begin($game, [$creator, $creatorDeck], [$user, $deck]);
        $this->entityManager->flush();

        return $steps;
    }

    /**
     * Партия с компьютером начинается сразу. Колода компьютера — случайная базовая.
     * Если первым ходит компьютер, его ход уже сыгран — он в шагах.
     */
    public function playComputer(User $user, Deck $deck): GameStarted
    {
        $this->checkDeck($user, $deck);
        $baseDecks = $this->deckRepository->findBy(['owner' => null], ['id' => 'ASC']);
        if ($baseDecks === []) {
            throw new GameException('Нет базовых колод для компьютера — загрузите контент.');
        }
        $computerDeck = $baseDecks[array_rand($baseDecks)];

        $game = new Game($user, $deck);
        $this->entityManager->persist($game);
        $steps = $this->begin($game, [$user, $deck], [null, $computerDeck]);
        $this->entityManager->flush();

        return new GameStarted($game, $steps);
    }

    /**
     * Ход игрока. Недопустимый ход (IllegalActionException) партию не меняет и не сохраняется.
     * В партии с компьютером, если ход перешёл к нему, он сразу играет свой — события вместе.
     * Если время хода уже вышло, ход сначала переходит сопернику (expireTurn) — и ход игрока,
     * скорее всего, окажется не в свой ход.
     *
     * @return list<Step> ход игрока и, в партии с компьютером, ходы компьютера — по действию на шаг
     *
     * @throws IllegalActionException
     */
    public function act(Game $game, ActionInterface $action): array
    {
        $this->expireTurn($game);
        if ($game->getStatus() !== GameStatus::ACTIVE) {
            throw new GameException('Партия не идёт.');
        }

        $state = $this->state($game);
        $turn = $state->turn;
        $actor = $state->activePlayer;
        $steps = [$this->step($state, $this->engine->apply($state, $action), $actor)];
        array_push($steps, ...$this->computerTurns($game, $state));

        $this->record($game, $steps);
        $this->save($game, $state, turnChanged: $state->turn !== $turn);

        return $steps;
    }

    /**
     * Время хода вышло — ход завершается за игрока. Фонового процесса нет: проверка — при любом обращении
     * к партии (страница соперника сама запросит партию, когда его отсчёт дойдёт до нуля).
     *
     * @return list<Step> пусто, если время не вышло
     */
    public function expireTurn(Game $game): array
    {
        $deadline = $game->getTurnDeadline();
        if ($game->getStatus() !== GameStatus::ACTIVE || $deadline === null || $this->clock->now() < $deadline) {
            return [];
        }

        $state = $this->state($game);
        $actor = $state->activePlayer;
        $steps = [$this->step($state, $this->engine->apply($state, new EndTurn($actor)), $actor)];
        array_push($steps, ...$this->computerTurns($game, $state));
        $this->record($game, $steps);
        $this->save($game, $state, turnChanged: true);

        return $steps;
    }

    /**
     * Индекс игрока в партии. Не участник — доступа нет.
     */
    public function playerIndex(Game $game, User $user): int
    {
        return $game->playerIndexOf($user) ?? throw new AccessDeniedException('Вы не участвуете в этой партии.');
    }

    public function state(Game $game): GameState
    {
        return GameState::fromArray($game->getState() ?? throw new GameException('Партия ещё не началась.'));
    }

    /**
     * Старт партии: жребий решает, кто ходит первым; null вместо пользователя — компьютер.
     *
     * @param array{0: ?User, 1: Deck} $creator
     * @param array{0: ?User, 1: Deck} $opponent
     *
     * @return list<Step>
     */
    private function begin(Game $game, array $creator, array $opponent): array
    {
        $seed = random_int(0, PHP_INT_MAX >> 32);
        $players = [$creator, $opponent];
        if ((new Randomizer(new Mt19937($seed)))->getInt(0, 1) === 1) {
            $players = array_reverse($players);
        }
        [[$first, $firstDeck], [$second, $secondDeck]] = $players;

        $state = GameState::create(
            $this->cardDefinitionFactory->fromDeck($firstDeck),
            $this->cardDefinitionFactory->fromDeck($secondDeck),
            $this->neutralBuildings(),
            $seed,
            $this->tokens(),
        );
        $steps = [$this->step($state, $this->engine->apply($state, new StartGame()), null)];

        $computer = match (null) {
            $first => 0,
            $second => 1,
            default => null,
        };
        $game->start($first, $second, $seed, $state->toArray(), $computer);
        array_push($steps, ...$this->computerTurns($game, $state));
        $this->record($game, $steps);
        $this->save($game, $state, turnChanged: true);

        return $steps;
    }

    /**
     * Пока ход компьютера — он играет; каждое его действие — отдельный шаг.
     *
     * @return list<Step>
     */
    private function computerTurns(Game $game, GameState $state): array
    {
        $steps = [];
        while (!$state->isOver() && $game->isComputerTurn($state->activePlayer)) {
            $computer = $state->activePlayer;
            $this->computerPlayer->playTurn(
                $state,
                $computer,
                function (array $events) use ($state, $computer, &$steps): void {
                    $steps[] = $this->step($state, $events, $computer);
                },
            );
        }

        return $steps;
    }

    /**
     * @param list<GameEvent> $events
     * @param int|null        $actor  чей был ход перед действием; null — старт партии
     */
    private function step(GameState $state, array $events, ?int $actor): Step
    {
        return new Step($events, $state->toArray(), $actor);
    }

    /**
     * Шаги — в ходы партии (журнал после перезагрузки страницы). Записываются вместе с состоянием (save).
     *
     * @param list<Step> $steps
     */
    private function record(Game $game, array $steps): void
    {
        foreach ($steps as $step) {
            $this->entityManager->persist(new GameMove($game, $step->actor, $this->gameView->normalize($step->events)));
        }
    }

    /**
     * @param bool $turnChanged ход перешёл к другому игроку — у него снова полное время
     */
    private function save(Game $game, GameState $state, bool $turnChanged = false): void
    {
        $game->saveState($state->toArray());
        if ($turnChanged) {
            $game->setTurnDeadline($this->clock->now()->modify(sprintf('+%d seconds', $this->turnSeconds)));
        }
        if ($state->winner !== null) {
            $game->finish($state->winner);
        }
        $this->entityManager->flush();
    }

    /**
     * Играть можно базовой колодой или своей.
     */
    private function checkDeck(User $user, Deck $deck): void
    {
        if (!$deck->isBase() && $deck->getOwner() !== $user) {
            throw new AccessDeniedException('Это чужая колода.');
        }
    }

    /**
     * Карты, которые появляются по ходу партии, — предметы (их куёт Кузница).
     *
     * @return list<CardDefinition>
     */
    private function tokens(): array
    {
        return array_map($this->cardDefinitionFactory->fromCard(...), $this->cardRepository->findByCardTypeSlug(CardKind::Item->value));
    }

    /**
     * @return list<CardDefinition>
     */
    private function neutralBuildings(): array
    {
        $buildings = $this->cardRepository->findByCardTypeSlug(CardKind::Building->value);
        if (count($buildings) < GameRules::CAPTURE_POINTS) {
            throw new GameException(sprintf('В контенте нужно не меньше %d нейтральных построек.', GameRules::CAPTURE_POINTS));
        }

        return array_map($this->cardDefinitionFactory->fromCard(...), $buildings);
    }
}
