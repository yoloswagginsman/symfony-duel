<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\GameMove;
use App\Game\Engine\Step;
use App\Game\Model\GameState;
use App\Game\View\GameView;
use Psr\Clock\ClockInterface;

/**
 * Партия для клиента — одинаково в ответе API и в событии Mercure.
 */
readonly class GamePresenter
{
    private const string COMPUTER_NAME = 'Компьютер';

    public function __construct(
        private GameService $gameService,
        private GameView $gameView,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Партия и её состояние глазами игрока $player.
     * events — что произошло (все шаги подряд), steps — то же по действиям, с полем после каждого:
     * клиент проигрывает их по очереди.
     *
     * @param list<Step> $steps
     *
     * @return array<string, mixed>
     */
    public function full(Game $game, int $player, array $steps = []): array
    {
        return $this->fullWith($game, $player, $steps);
    }

    /**
     * То же и журнал партии — последние ходы (для страницы после перезагрузки).
     *
     * @param list<GameMove> $moves
     *
     * @return array<string, mixed>
     */
    public function fullWithHistory(Game $game, int $player, array $moves, array $steps = []): array
    {
        return $this->fullWith($game, $player, $steps) + [
            'history' => array_map(
                fn (GameMove $move) => ['actor' => $move->getActor(), 'events' => $this->gameView->hide($move->getEvents(), $player)],
                $moves,
            ),
        ];
    }

    /**
     * @param list<Step> $steps
     *
     * @return array<string, mixed>
     */
    private function fullWith(Game $game, int $player, array $steps): array
    {
        return [
            'game' => $this->summary($game),
            'state' => $game->getState() !== null ? $this->gameView->state($this->gameService->state($game), $player) : null,
            'events' => $this->gameView->events(Step::eventsOf($steps), $player),
            'steps' => array_map(
                fn (Step $step) => [
                    'events' => $this->gameView->events($step->events, $player),
                    'state' => $this->gameView->state(GameState::fromArray($step->state), $player),
                ],
                $steps,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Game $game): array
    {
        $player = static fn (int $index) => match (true) {
            $game->getComputerPlayer() === $index => ['id' => null, 'nickname' => self::COMPUTER_NAME, 'computer' => true],
            $game->playerAt($index) !== null => ['id' => $game->playerAt($index)->getId(), 'nickname' => $game->playerAt($index)->getNickname(), 'computer' => false],
            default => null,
        };

        return [
            'id' => $game->getId(),
            'status' => $game->getStatus()->value,
            'players' => [$player(0), $player(1)],
            'winner' => $game->getWinnerIndex(),
            'version' => $game->getVersion(),
            // Отсчёт хода в браузере: срок и время сервера (часы клиента могут спешить или отставать)
            'turnDeadline' => $game->getTurnDeadline()?->format(\DATE_ATOM),
            'serverTime' => $this->clock->now()->format(\DATE_ATOM),
            'updatedAt' => $game->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
