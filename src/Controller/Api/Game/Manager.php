<?php

namespace App\Controller\Api\Game;

use App\Dto\Api\DeckChoiceDto;
use App\Dto\Api\GameActionDto;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\User;
use App\Repository\DeckRepository;
use App\Repository\GameMoveRepository;
use App\Repository\GameRepository;
use App\Service\Game\GameNotifier;
use App\Service\Game\GamePresenter;
use App\Service\Game\GameService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API партий: запрос → GameService → ответ глазами игрока (GamePresenter).
 * После старта и каждого хода участники получают событие через Mercure (GameNotifier).
 */
readonly class Manager
{
    // Сколько последних ходов отдавать в журнал страницы
    private const int HISTORY_MOVES = 300;

    public function __construct(
        private GameService $gameService,
        private GameRepository $gameRepository,
        private GameMoveRepository $gameMoveRepository,
        private DeckRepository $deckRepository,
        private GamePresenter $presenter,
        private GameNotifier $notifier,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(User $user): array
    {
        return [
            'waiting' => array_map($this->presenter->summary(...), $this->gameRepository->findWaitingFor($user)),
            'mine' => array_map($this->presenter->summary(...), $this->gameRepository->findUnfinishedOf($user)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(User $user, Request $request): array
    {
        $game = $this->gameService->create($user, $this->deck($request));

        return ['game' => $this->presenter->summary($game)];
    }

    /**
     * Партия с компьютером — начинается сразу.
     *
     * @return array<string, mixed>
     */
    public function playComputer(User $user, Request $request): array
    {
        $started = $this->gameService->playComputer($user, $this->deck($request));
        $this->notifier->publish($started->game, $started->steps);

        return $this->presenter->full($started->game, $this->gameService->playerIndex($started->game, $user), $started->steps);
    }

    /**
     * @return array<string, mixed>
     */
    public function join(Game $game, User $user, Request $request): array
    {
        $steps = $this->gameService->join($game, $user, $this->deck($request));
        $this->notifier->publish($game, $steps);

        return $this->presenter->full($game, $this->gameService->playerIndex($game, $user), $steps);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Game $game, User $user): array
    {
        $player = $this->gameService->playerIndex($game, $user);

        // Вышло время хода — ход переходит, участники получают событие
        $steps = $this->gameService->expireTurn($game);
        if ($steps !== []) {
            $this->notifier->publish($game, $steps);
        }

        // С журналом партии — чтобы страница восстановила его после перезагрузки
        return $this->presenter->fullWithHistory($game, $player, $this->gameMoveRepository->findLastOf($game, self::HISTORY_MOVES), $steps);
    }

    /**
     * @return array<string, mixed>
     */
    public function act(Game $game, User $user, Request $request): array
    {
        $player = $this->gameService->playerIndex($game, $user);
        $dto = $this->validated(GameActionDto::fromArray($request->toArray()));

        // Время хода вышло — ход уже перешёл; соперник узнает об этом, даже если ход ниже будет отклонён
        $expired = $this->gameService->expireTurn($game);
        if ($expired !== []) {
            $this->notifier->publish($game, $expired);
        }

        $steps = $this->gameService->act($game, $dto->toAction($player));
        $this->notifier->publish($game, $steps);

        // Игроку — и переход хода по времени, если он был, и его ход
        return $this->presenter->full($game, $player, [...$expired, ...$steps]);
    }

    /**
     * @return array{hub: string, topic: string, token: ?string}
     */
    public function subscription(Game $game, User $user): array
    {
        $this->gameService->playerIndex($game, $user);

        return $this->notifier->subscription($user, $game);
    }

    private function deck(Request $request): Deck
    {
        $dto = $this->validated(DeckChoiceDto::fromArray($request->toArray()));

        return $this->deckRepository->find($dto->deckId) ?? throw new NotFoundHttpException('Колода не найдена.');
    }

    /**
     * @template T of object
     *
     * @param T $dto
     *
     * @return T
     */
    private function validated(object $dto): object
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($dto, $violations);
        }

        return $dto;
    }
}
