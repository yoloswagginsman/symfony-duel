<?php

namespace App\Game\View;

use App\Game\Ability\AbilityRegistry;
use App\Game\Engine\GameContext;
use App\Game\Enum\Keyword;
use App\Game\Event\GameEvent;
use App\Game\Model\AbilityRef;
use App\Game\Model\CapturePoint;
use App\Game\Model\CardInstance;
use App\Game\Model\GameState;
use App\Game\Model\PlayerState;

/**
 * Партия глазами игрока $viewer — то, что уходит клиенту. Скрыто от него:
 * рука и колода противника (только число карт), существа противника под Туманом (Укрытие),
 * карты, которые противник взял из колоды.
 */
final readonly class GameView
{
    // Имена событий, которые скрываются от противника (короткие имена классов — как в normalize())
    private const string CARD_DRAWN = 'CardDrawn';
    private const string CARD_CREATED = 'CardCreated';

    public function __construct(
        private AbilityRegistry $abilities,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function state(GameState $state, int $viewer): array
    {
        $game = new GameContext($state, $this->abilities);

        return [
            'you' => $viewer,
            'activePlayer' => $state->activePlayer,
            'turn' => $state->turn,
            'winner' => $state->winner,
            'capturePoints' => array_map(
                fn (CapturePoint $point) => [
                    'building' => $this->card($game, $point->building),
                    'holder' => $point->holder !== null ? $this->card($game, $point->holder) : null,
                ],
                $state->capturePoints,
            ),
            'players' => array_map(fn (PlayerState $player) => $this->player($game, $player, $viewer), $state->players),
        ];
    }

    /**
     * События действия для игрока: карты — по id, добор противника — без карты.
     *
     * @param list<GameEvent> $events
     *
     * @return list<array<string, mixed>>
     */
    public function events(array $events, int $viewer): array
    {
        return $this->hide($this->normalize($events), $viewer);
    }

    /**
     * События как массивы — полностью, без сокрытия (так они хранятся в ходах партии): карты — по id.
     *
     * @param list<GameEvent> $events
     *
     * @return list<array<string, mixed>>
     */
    public function normalize(array $events): array
    {
        return array_map(static function (GameEvent $event) {
            $data = ['type' => (new \ReflectionClass($event))->getShortName()];
            foreach (get_object_vars($event) as $field => $value) {
                $data[$field] = $value instanceof CardInstance ? $value->id : $value;
            }

            return $data;
        }, $events);
    }

    /**
     * Что скрыть от игрока: какую карту взял или получил противник.
     *
     * @param list<array<string, mixed>> $events normalize()
     *
     * @return list<array<string, mixed>>
     */
    public function hide(array $events, int $viewer): array
    {
        return array_map(static function (array $event) use ($viewer) {
            if (in_array($event['type'], [self::CARD_DRAWN, self::CARD_CREATED], true) && $event['player'] !== $viewer) {
                $event['card'] = null;
            }

            return $event;
        }, $events);
    }

    /**
     * @return array<string, mixed>
     */
    private function player(GameContext $game, PlayerState $player, int $viewer): array
    {
        $isViewer = $player->index === $viewer;
        $cards = fn (array $cards) => array_map(fn (CardInstance $card) => $this->card($game, $card), $cards);

        return [
            'index' => $player->index,
            'fortification' => $player->fortification,
            'mana' => $player->mana,
            'maxMana' => $player->maxMana,
            'deckCount' => count($player->deck),
            'handCount' => count($player->hand),
            'hand' => $isViewer ? $cards($player->hand) : null,
            'board' => array_map(
                fn (?CardInstance $card) => match (true) {
                    $card === null => null,
                    !$isViewer && $game->hasKeyword($card, Keyword::Cover) => ['hidden' => true],
                    default => $this->card($game, $card),
                },
                $player->board,
            ),
            'landscape' => $player->landscape !== null ? $this->card($game, $player->landscape) : null,
            'graveyard' => $cards($player->graveyard),
        ];
    }

    /**
     * Атака и здоровье — текущие, со способностями и аурами (для карт вне поля — как в описании).
     *
     * @return array<string, mixed>
     */
    private function card(GameContext $game, CardInstance $card): array
    {
        $definition = $card->definition;
        $inPlay = $game->state->findInPlay($card->id) !== null;

        return [
            'id' => $card->id,
            'owner' => $card->owner,
            'vendorCode' => $definition->vendorCode,
            'name' => $definition->name,
            'kind' => $definition->kind->value,
            'manaCost' => $definition->manaCost,
            'attack' => $inPlay ? $game->attack($card) : $definition->attack,
            'health' => $inPlay ? $game->health($card) : $definition->health,
            // Как в описании карты — чтобы показать, что изменили ауры, ярость и урон
            'baseAttack' => $definition->attack,
            'baseHealth' => $definition->health,
            'abilities' => array_map(static fn (AbilityRef $ref) => $ref->toArray(), $definition->abilities),
            'keywords' => $inPlay
                ? array_values(array_map(
                    static fn (Keyword $keyword) => $keyword->value,
                    array_filter(Keyword::cases(), static fn (Keyword $keyword) => $game->hasKeyword($card, $keyword)),
                ))
                : [],
            // Надетые предметы: [{name, attack, health}]
            'equipment' => $card->state['equipment'] ?? [],
            'summonedThisTurn' => $card->summonedThisTurn,
            'actedThisTurn' => $card->actedThisTurn,
        ];
    }
}
