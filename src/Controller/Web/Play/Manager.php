<?php

namespace App\Controller\Web\Play;

use App\Controller\Web\Play\Output\LobbyData;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\User;
use App\Repository\DeckRepository;
use App\Repository\GameRepository;
use App\Service\Game\GameNotifier;
use App\Service\Game\GameService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Лобби на сайте: те же действия, что в API (GameService), но формами с сессией.
 */
readonly class Manager
{
    public function __construct(
        private GameService $gameService,
        private GameNotifier $notifier,
        private GameRepository $gameRepository,
        private DeckRepository $deckRepository,
    ) {
    }

    public function lobby(User $user): LobbyData
    {
        return new LobbyData(
            decks: $this->deckRepository->findAvailableFor($user),
            waitingGames: $this->gameRepository->findWaitingFor($user),
            myGames: $this->gameRepository->findUnfinishedOf($user),
        );
    }

    public function playComputer(User $user, Request $request): Game
    {
        $started = $this->gameService->playComputer($user, $this->deck($request));
        $this->notifier->publish($started->game, $started->steps);

        return $started->game;
    }

    public function create(User $user, Request $request): Game
    {
        return $this->gameService->create($user, $this->deck($request));
    }

    /**
     * Создатель партии ждёт на её странице — узнает о старте через Mercure.
     */
    public function join(Game $game, User $user, Request $request): void
    {
        $this->notifier->publish($game, $this->gameService->join($game, $user, $this->deck($request)));
    }

    /**
     * Колода из формы лобби (поле deck).
     */
    private function deck(Request $request): Deck
    {
        return $this->deckRepository->find($request->getPayload()->getInt('deck'))
            ?? throw new NotFoundHttpException('Выберите колоду.');
    }
}
