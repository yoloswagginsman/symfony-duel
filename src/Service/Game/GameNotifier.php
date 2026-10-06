<?php

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Game\Engine\Step;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Update;

/**
 * Рассылает участникам партии, что произошло, через хаб Mercure.
 * У каждого игрока свой приватный топик и своё содержимое: чужая рука и туман скрыты (GameView).
 */
readonly class GameNotifier
{
    public function __construct(
        private HubInterface $hub,
        private GamePresenter $presenter,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Приватный топик игрока в партии: у каждого участника свой — содержимое разное.
     */
    public static function topic(User $user, Game $game): string
    {
        return sprintf('/users/%d/games/%d', $user->getId(), $game->getId());
    }

    /**
     * Подписка на свою партию: адрес хаба, топик и токен, который пускает только в этот топик.
     *
     * @return array{hub: string, topic: string, token: ?string}
     */
    public function subscription(User $user, Game $game): array
    {
        $topic = self::topic($user, $game);

        return [
            'hub' => $this->hub->getPublicUrl(),
            'topic' => $topic,
            'token' => $this->hub->getFactory()?->create(
                [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])],
                ['sub' => (string) $user->getId()],
            ),
        ];
    }

    /**
     * Хаб недоступен — ход уже сохранён, игроки получат состояние запросом GET /api/games/{id}.
     *
     * @param list<Step> $steps
     */
    public function publish(Game $game, array $steps): void
    {
        foreach ([$game->getPlayer0(), $game->getPlayer1()] as $index => $user) {
            if ($user === null) {
                continue;
            }

            $update = new Update(
                self::topic($user, $game),
                json_encode($this->presenter->full($game, $index, $steps), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                private: true,
            );

            try {
                $this->hub->publish($update);
            } catch (RuntimeException $exception) {
                $this->logger->warning('Mercure: событие партии {game} не отправлено: {message}', [
                    'game' => $game->getId(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
