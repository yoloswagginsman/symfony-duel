<?php

namespace App\Game\Engine;

use App\Game\Ability\AbilityRegistry;
use App\Game\Action\ActionInterface;
use App\Game\Action\Attack;
use App\Game\Action\CapturePoint;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Action\StartGame;
use App\Game\Action\Surrender;
use App\Game\Enum\CardKind;
use App\Game\Enum\Keyword;
use App\Game\Event\AttackDeclared;
use App\Game\Event\CardPlayed;
use App\Game\Event\GameEvent;
use App\Game\Event\GameWon;
use App\Game\Event\LandscapeChanged;
use App\Game\Event\PlayerSurrendered;
use App\Game\Event\PointCaptured;
use App\Game\Event\TurnEnded;
use App\Game\Event\TurnStarted;
use App\Game\GameRules;
use App\Game\Model\CardInstance;
use App\Game\Model\GameState;
use App\Game\Model\PlayerState;

/**
 * Правила партии: проверяет действие игрока и выполняет его.
 * Сначала все проверки, потом изменения — недопустимое действие партию не трогает.
 */
final readonly class GameEngine
{
    public function __construct(
        private AbilityRegistry $abilities,
    ) {
    }

    /**
     * @return list<GameEvent> что произошло — по порядку
     *
     * @throws IllegalActionException
     */
    public function apply(GameState $state, ActionInterface $action): array
    {
        if ($state->isOver()) {
            throw new IllegalActionException('Партия окончена.');
        }

        $game = new GameContext($state, $this->abilities);

        match (true) {
            $action instanceof StartGame => $this->startGame($game),
            $action instanceof PlayCard => $this->playCard($game, $action),
            $action instanceof Attack => $this->attack($game, $action),
            $action instanceof CapturePoint => $this->capturePoint($game, $action),
            $action instanceof EndTurn => $this->endTurn($game, $action),
            $action instanceof Surrender => $this->surrender($game, $action),
            default => throw new IllegalActionException(sprintf('Неизвестное действие %s.', $action::class)),
        };

        $game->resolve();

        return $game->log();
    }

    private function startGame(GameContext $game): void
    {
        if ($game->state->turn !== 0) {
            throw new IllegalActionException('Партия уже начата.');
        }

        foreach (GameRules::START_HAND as $player => $size) {
            for ($i = 0; $i < $size; ++$i) {
                $game->draw($player);
            }
        }

        $this->startTurn($game, 0);
    }

    private function playCard(GameContext $game, PlayCard $action): void
    {
        $player = $this->activePlayer($game, $action->player);
        $card = $this->findInHand($game, $action->player, $action->cardId);
        $cost = $card->definition->manaCost;

        if ($cost > $player->mana) {
            throw new IllegalActionException(sprintf('Не хватает маны: нужно %d, есть %d.', $cost, $player->mana));
        }

        // Цель — существо в игре или ландшафт (например, для Dispel)
        $target = $action->targetId !== null
            ? $game->state->findInPlay($action->targetId)
                ?? $game->state->findLandscape($action->targetId)
                ?? throw new IllegalActionException('Цель не найдена на поле.')
            : null;

        match ($card->definition->kind) {
            CardKind::Creature => $this->checkFreeCell($game, $action),
            CardKind::Spell, CardKind::Landscape => null,
            CardKind::Item => $this->checkEquipTarget($action->player, $target),
            default => throw new IllegalActionException(sprintf('Карты типа «%s» пока не разыгрываются.', $card->definition->kind->value)),
        };

        // Проверки пройдены — меняем партию
        $player->hand = array_values(array_filter($player->hand, static fn (CardInstance $c) => $c !== $card));
        $player->mana -= $cost;

        match ($card->definition->kind) {
            CardKind::Creature => $this->summon($game, $card, $action->cell, $target),
            CardKind::Landscape => $this->changeLandscape($game, $card, $target),
            // Заклинание и предмет срабатывают (через свои способности на CardPlayed) и уходят в сброс
            default => $this->castSpell($game, $card, $target),
        };
    }

    private function summon(GameContext $game, CardInstance $card, int $cell, ?CardInstance $target): void
    {
        $game->emit(new CardPlayed($card, $target));
        $game->summon($card, $cell, $target);
    }

    private function castSpell(GameContext $game, CardInstance $card, ?CardInstance $target): void
    {
        $game->state->player($card->owner)->graveyard[] = $card;
        $game->emit(new CardPlayed($card, $target));
    }

    /**
     * Ландшафт ложится на свою половину поля, враждебный (Hostile) — на половину противника.
     * На половине один ландшафт: новый заменяет прежний (чей бы он ни был), прежний — в сброс своего хозяина.
     */
    private function changeLandscape(GameContext $game, CardInstance $card, ?CardInstance $target): void
    {
        $side = $game->hasKeyword($card, Keyword::Hostile) ? $game->state->opponentOf($card->owner) : $card->owner;
        $half = $game->state->player($side);

        $previous = $half->landscape;
        if ($previous !== null) {
            $game->state->player($previous->owner)->graveyard[] = $previous;
        }

        $half->landscape = $card;
        $game->emit(new CardPlayed($card, $target));
        $game->emit(new LandscapeChanged($card, $side, $previous));
    }

    private function attack(GameContext $game, Attack $action): void
    {
        $this->activePlayer($game, $action->player);
        $attacker = $this->findOwnOnBoard($game, $action->player, $action->attackerId);
        $this->checkReady($game, $attacker);

        if ($game->attack($attacker) <= 0) {
            throw new IllegalActionException('У существа нет атаки.');
        }

        $opponent = $game->state->opponentOf($action->player);
        $target = null;
        if ($action->targetId !== null) {
            $target = $game->state->findInPlay($action->targetId);
            if ($target === null || $target->owner !== $opponent) {
                throw new IllegalActionException('Цель — существо противника на поле или на точке.');
            }
        }

        if ($target !== null && $game->hasKeyword($target, Keyword::Cover)) {
            throw new IllegalActionException('Существо в Укрытии (туман) — атаковать его нельзя.');
        }
        $this->checkTaunt($game, $opponent, $target);
        if ($target !== null && $game->hasKeyword($target, Keyword::Flying) && !$game->hasKeyword($attacker, Keyword::Flying)) {
            throw new IllegalActionException('Летающее существо может атаковать только летающее.');
        }

        // Проверки пройдены — бой
        $attacker->actedThisTurn = true;
        $game->emit(new AttackDeclared($attacker, $target));

        if ($target === null) {
            $game->damageFortification($opponent, $game->attack($attacker), $attacker);

            return;
        }

        // Урон друг другу — одновременно: обе атаки считаются до нанесения урона
        $attackerDamage = $game->attack($attacker);
        $targetDamage = $game->attack($target);
        $game->damageCreature($target, $attackerDamage, $attacker);
        $game->damageCreature($attacker, $targetDamage, $target);
    }

    private function capturePoint(GameContext $game, CapturePoint $action): void
    {
        $this->activePlayer($game, $action->player);
        $creature = $this->findOwnOnBoard($game, $action->player, $action->creatureId);
        $this->checkReady($game, $creature);

        $point = $game->state->capturePoints[$action->point] ?? throw new IllegalActionException('Нет такой точки сопряжения.');
        if ($point->holder !== null) {
            throw new IllegalActionException('Точка занята — сначала выбейте существо с неё.');
        }

        // Переход на точку — действие существа за этот ход
        $game->state->removeFromPlay($creature);
        $point->holder = $creature;
        $creature->actedThisTurn = true;
        $game->emit(new PointCaptured($creature, $action->point));
    }

    private function endTurn(GameContext $game, EndTurn $action): void
    {
        $this->activePlayer($game, $action->player);

        $game->emit(new TurnEnded($action->player));
        $this->startTurn($game, $game->state->opponentOf($action->player));
    }

    /**
     * Сдаться можно и в чужой ход — партия сразу заканчивается победой соперника.
     */
    private function surrender(GameContext $game, Surrender $action): void
    {
        if ($game->state->turn === 0) {
            throw new IllegalActionException('Партия ещё не начата.');
        }

        $winner = $game->state->opponentOf($action->player);
        $game->state->winner = $winner;
        $game->emit(new PlayerSurrendered($action->player));
        $game->emit(new GameWon($winner));
    }

    private function startTurn(GameContext $game, int $playerIndex): void
    {
        $state = $game->state;
        $state->activePlayer = $playerIndex;
        ++$state->turn;

        $player = $state->player($playerIndex);
        $player->maxMana = min(GameRules::MAX_MANA, $player->maxMana + 1);
        $player->mana = $player->maxMana;

        foreach ($state->cardsInPlay($playerIndex) as $card) {
            $card->summonedThisTurn = false;
            $card->actedThisTurn = false;
        }

        $game->emit(new TurnStarted($playerIndex, $state->turn));
        $game->draw($playerIndex);
    }

    // ─── Проверки ──────────────────────────────────────────────

    private function activePlayer(GameContext $game, int $player): PlayerState
    {
        if ($game->state->turn === 0) {
            throw new IllegalActionException('Партия ещё не начата.');
        }
        if ($game->state->activePlayer !== $player) {
            throw new IllegalActionException('Сейчас ход другого игрока.');
        }

        return $game->state->player($player);
    }

    private function findInHand(GameContext $game, int $player, int $cardId): CardInstance
    {
        foreach ($game->state->player($player)->hand as $card) {
            if ($card->id === $cardId) {
                return $card;
            }
        }

        throw new IllegalActionException('Такой карты нет в руке.');
    }

    private function findOwnOnBoard(GameContext $game, int $player, int $cardId): CardInstance
    {
        foreach ($game->state->player($player)->board as $card) {
            if ($card?->id === $cardId) {
                return $card;
            }
        }

        if ($game->state->findInPlay($cardId)?->owner === $player) {
            throw new IllegalActionException('Существо на точке сопряжения не атакует и не двигается.');
        }

        throw new IllegalActionException('Такого существа нет на вашем поле.');
    }

    private function checkFreeCell(GameContext $game, PlayCard $action): void
    {
        $board = $game->state->player($action->player)->board;

        if ($action->cell === null || !array_key_exists($action->cell, $board)) {
            throw new IllegalActionException(sprintf('Укажите ячейку поля: 0–%d.', GameRules::BOARD_SIZE - 1));
        }
        if ($board[$action->cell] !== null) {
            throw new IllegalActionException('Ячейка занята.');
        }
    }

    /**
     * Предмет надевается на своё существо (на поле или на точке).
     */
    private function checkEquipTarget(int $player, ?CardInstance $target): void
    {
        if ($target === null || $target->owner !== $player || $target->definition->kind !== CardKind::Creature) {
            throw new IllegalActionException('Предмет надевается на своё существо — выберите его.');
        }
    }

    private function checkReady(GameContext $game, CardInstance $creature): void
    {
        if ($creature->actedThisTurn) {
            throw new IllegalActionException('Существо уже действовало в этот ход.');
        }
        if ($creature->summonedThisTurn && !$game->hasKeyword($creature, Keyword::Charge)) {
            throw new IllegalActionException('Существо только вышло на поле — атакует со следующего хода (кроме «Рывка»).');
        }
    }

    /**
     * Пока у противника есть существо с Провокацией, атаковать можно только такое существо.
     * Исключение — существо на точке сопряжения: Провокация точки не прикрывает.
     * (Провокацию нельзя спрятать в Укрытие — см. GameContext::hasKeyword.)
     */
    private function checkTaunt(GameContext $game, int $opponent, ?CardInstance $target): void
    {
        if ($target !== null && $game->state->holdsPoint($target)) {
            return;
        }

        $taunters = array_filter(
            $game->state->cardsInPlay($opponent),
            static fn (CardInstance $card) => $game->hasKeyword($card, Keyword::Taunt),
        );

        if ($taunters !== [] && !in_array($target, $taunters, true)) {
            throw new IllegalActionException('Мешает Провокация: сначала атакуйте существо с ней.');
        }
    }
}
