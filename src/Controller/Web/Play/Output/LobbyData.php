<?php

namespace App\Controller\Web\Play\Output;

use App\Entity\Deck;
use App\Entity\Game;

/**
 * Лобби: чем играть и во что.
 */
readonly class LobbyData
{
    /**
     * @param list<Deck> $decks        базовые и свои колоды
     * @param list<Game> $waitingGames партии других игроков, ждут соперника
     * @param list<Game> $myGames      свои незавершённые партии
     */
    public function __construct(
        public array $decks,
        public array $waitingGames,
        public array $myGames,
    ) {
    }
}
