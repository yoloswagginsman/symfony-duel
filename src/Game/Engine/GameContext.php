<?php

namespace App\Game\Engine;

use App\Game\Ability\AbilityContext;
use App\Game\Ability\AbilityRegistry;
use App\Game\Ability\Contract\AbilityInterface;
use App\Game\Ability\Contract\AuraAbilityInterface;
use App\Game\Ability\Contract\DamagePreventionInterface;
use App\Game\Ability\Contract\EffectAbilityInterface;
use App\Game\Ability\Contract\KeywordAbilityInterface;
use App\Game\Ability\Contract\KeywordAuraInterface;
use App\Game\Ability\Contract\StatModifierInterface;
use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Enum\CardKind;
use App\Game\Enum\Keyword;
use App\Game\Event\CardCreated;
use App\Game\Event\CardDrawn;
use App\Game\Event\CardPlayed;
use App\Game\Event\CreatureDamaged;
use App\Game\Event\CreatureBuffed;
use App\Game\Event\CreatureHealed;
use App\Game\Event\CreatureSummoned;
use App\Game\Event\CreatureDied;
use App\Game\Event\FortificationDamaged;
use App\Game\Event\FortificationRepaired;
use App\Game\Event\GameEvent;
use App\Game\Event\GameWon;
use App\Game\Event\LandscapeEnded;
use App\Game\Event\ManaGained;
use App\Game\GameRules;
use App\Game\Model\CardInstance;
use App\Game\Model\GameState;

/**
 * Партия на время одного действия: очередь событий, характеристики карт с учётом способностей
 * и действия над партией (урон, лечение, добор), которыми пользуются и правила, и способности.
 */
final class GameContext
{
    // Защита от бесконечной цепочки способностей, вызывающих друг друга
    private const int MAX_EVENTS = 1000;

    /** @var list<GameEvent> */
    private array $queue = [];

    /** @var list<GameEvent> */
    private array $log = [];

    public function __construct(
        public readonly GameState $state,
        private readonly AbilityRegistry $abilities,
    ) {
    }

    // ─── События ───────────────────────────────────────────────

    public function emit(GameEvent $event): void
    {
        $this->queue[] = $event;
        $this->log[] = $event;
    }

    /**
     * Раздаёт события способностям, пока очередь не опустеет (способности могут порождать новые):
     * сначала разовые эффекты (розыгрыш заклинания, Призыв существа), потом реакции всех слушателей.
     * После каждого события — погибшие уходят в сброс, проверяется победа.
     */
    public function resolve(): void
    {
        $processed = 0;

        while (($event = array_shift($this->queue)) !== null) {
            if (++$processed > self::MAX_EVENTS) {
                throw new \LogicException('Слишком длинная цепочка событий — способности зациклились.');
            }

            $this->applyEffects($event);
            foreach ($this->listeners($event) as $card) {
                foreach ($this->abilitiesOf($card, TriggeredAbilityInterface::class) as [$ability, $context]) {
                    $ability->onEvent($event, $context);
                }
            }

            $this->removeDead();
            $this->checkWinner();
        }
    }

    /**
     * @return list<GameEvent> все события действия — по порядку
     */
    public function log(): array
    {
        return $this->log;
    }

    // ─── Характеристики с учётом способностей ──────────────────

    public function attack(CardInstance $card): int
    {
        $attack = ($card->definition->attack ?? 0) + ($card->state['attack_bonus'] ?? 0);
        foreach ($this->abilitiesOf($card, StatModifierInterface::class) as [$ability, $context]) {
            $attack += $ability->attackBonus($context);
        }
        foreach ($this->auras() as [$aura, $context]) {
            $attack += $aura->attackBonusFor($card, $context);
        }

        return max(0, $attack);
    }

    public function health(CardInstance $card): int
    {
        $health = ($card->definition->health ?? 0) + ($card->state['health_bonus'] ?? 0);
        foreach ($this->abilitiesOf($card, StatModifierInterface::class) as [$ability, $context]) {
            $health += $ability->healthBonus($context);
        }
        foreach ($this->auras() as [$aura, $context]) {
            $health += $aura->healthBonusFor($card, $context);
        }

        return $health - $card->damage;
    }

    /**
     * Кто владеет постройкой на точке сопряжения; null — точка свободна (бонус не действует).
     */
    public function controllerOf(CardInstance $building): ?int
    {
        return $this->state->controllerOf($building);
    }

    /**
     * Ключевое слово — от своих способностей карты или от аур (например, Укрытие от Тумана).
     */
    public function hasKeyword(CardInstance $card, Keyword $keyword): bool
    {
        // Существо с Провокацией спрятать нельзя: оно всегда на виду и принимает удары на себя
        if ($keyword === Keyword::Cover && $this->hasKeyword($card, Keyword::Taunt)) {
            return false;
        }

        foreach ($this->abilitiesOf($card, KeywordAbilityInterface::class) as [$ability, $context]) {
            if (in_array($keyword, $ability->keywords($context), true)) {
                return true;
            }
        }
        foreach ($this->sources() as $source) {
            foreach ($this->abilitiesOf($source, KeywordAuraInterface::class) as [$aura, $context]) {
                if (in_array($keyword, $aura->keywordsFor($card, $context), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ─── Действия над партией ──────────────────────────────────

    /**
     * Выставить существо в ячейку своего поля — срабатывает его Призыв.
     * Из руки (правила) или вызванное другой картой (способности).
     */
    public function summon(CardInstance $creature, int $cell, ?CardInstance $target = null): void
    {
        $this->state->player($creature->owner)->board[$cell] = $creature;
        $creature->summonedThisTurn = true;
        $this->emit(new CreatureSummoned($creature, $cell, $target));
    }

    public function damageCreature(CardInstance $target, int $amount, ?CardInstance $source = null): void
    {
        foreach ($this->abilitiesOf($target, DamagePreventionInterface::class) as [$ability, $context]) {
            $amount = $ability->absorb($amount, $context);
        }
        if ($amount <= 0) {
            return;
        }

        $target->damage += $amount;
        $this->emit(new CreatureDamaged($target, $amount, $source));
    }

    public function damageFortification(int $player, int $amount, ?CardInstance $source = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->state->player($player)->fortification -= $amount;
        $this->emit(new FortificationDamaged($player, $amount, $source));
    }

    /**
     * Лечение существа: снимает полученный урон, выше максимума здоровья не поднимает.
     */
    public function healCreature(CardInstance $target, int $amount, ?CardInstance $source = null): void
    {
        $healed = min($amount, $target->damage);
        if ($healed <= 0) {
            return;
        }

        $target->damage -= $healed;
        $this->emit(new CreatureHealed($target, $healed, $source));
    }

    /**
     * Ремонт фортификации — не выше её максимума.
     */
    public function repairFortification(int $player, int $amount, ?CardInstance $source = null): void
    {
        $fortification = &$this->state->player($player)->fortification;
        $repaired = min($amount, GameRules::FORTIFICATION - $fortification);
        if ($repaired <= 0) {
            return;
        }

        $fortification += $repaired;
        $this->emit(new FortificationRepaired($player, $repaired, $source));
    }

    /**
     * Усилить существо навсегда (пока живо) — например, надетый предмет.
     */
    public function buffCreature(CardInstance $creature, int $attack, int $health, ?CardInstance $source = null): void
    {
        $creature->state['attack_bonus'] = ($creature->state['attack_bonus'] ?? 0) + $attack;
        $creature->state['health_bonus'] = ($creature->state['health_bonus'] ?? 0) + $health;
        $this->emit(new CreatureBuffed($creature, $attack, $health, $source));
    }

    /**
     * Надеть предмет на существо: усиление и запись «что надето» (видно при наведении на существо).
     */
    public function equip(CardInstance $creature, CardInstance $item, int $attack, int $health): void
    {
        $creature->state['equipment'][] = ['name' => $item->definition->name, 'attack' => $attack, 'health' => $health];
        $this->buffCreature($creature, $attack, $health, $item);
    }

    /**
     * Создать карту в руку игрока (не из колоды) — по описанию из GameState::$tokens.
     * Описания нет (партия начата до появления предметов) — ничего не происходит.
     */
    public function createCard(int $player, string $vendorCode, ?CardInstance $source = null): ?CardInstance
    {
        $definition = $this->state->tokens[$vendorCode] ?? null;
        if ($definition === null) {
            return null;
        }

        $card = $this->state->newInstance($definition, $player);
        $this->state->player($player)->hand[] = $card;
        $this->emit(new CardCreated($player, $card, $source));

        return $card;
    }

    /**
     * Уничтожить существо независимо от здоровья (например, Poison). Уйдёт в сброс при разборе событий.
     */
    public function destroy(CardInstance $creature): void
    {
        $creature->state['destroyed'] = true;
    }

    /**
     * Существо стоит в ячейке поля этого игрока (на точке сопряжения — нет: точки ничьи).
     */
    public function isOnBoardOf(CardInstance $card, int $player): bool
    {
        return in_array($card, $this->state->player($player)->board, true);
    }

    /**
     * Убрать ландшафт с поля в сброс разыгравшего: истёк срок (Duration) или развеяли (Dispel).
     */
    public function endLandscape(CardInstance $landscape, ?CardInstance $source = null): void
    {
        $side = $this->state->sideOf($landscape);
        if ($side === null) {
            return;
        }

        $this->state->player($side)->landscape = null;
        $this->state->player($landscape->owner)->graveyard[] = $landscape;
        $this->emit(new LandscapeEnded($landscape, $source));
    }

    /**
     * Дополнительная мана на этот ход — сверх обычной, максимум маны не меняется.
     */
    public function gainMana(int $player, int $amount, ?CardInstance $source = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->state->player($player)->mana += $amount;
        $this->emit(new ManaGained($player, $amount, $source));
    }

    /**
     * Взять верхнюю карту колоды. Пустая колода в v0.1 — просто без добора.
     */
    public function draw(int $player): void
    {
        $playerState = $this->state->player($player);
        $card = array_shift($playerState->deck);
        if ($card === null) {
            return;
        }

        $playerState->hand[] = $card;
        $this->emit(new CardDrawn($player, $card));
    }

    // ─── Внутреннее ────────────────────────────────────────────

    /**
     * Способности карты нужного вида — с контекстом. Способность без класса пропускается.
     *
     * @template T of AbilityInterface
     *
     * @param class-string<T> $type
     *
     * @return list<array{T, AbilityContext}>
     */
    private function abilitiesOf(CardInstance $card, string $type): array
    {
        $result = [];
        foreach ($card->definition->abilities as $ref) {
            $ability = $this->abilities->get($ref->slug);
            if ($ability instanceof $type) {
                $result[] = [$ability, new AbilityContext($this, $card, $ref->value)];
            }
        }

        return $result;
    }

    /**
     * Разовые эффекты карты: у заклинания — при розыгрыше, у существа — при Призыве.
     */
    private function applyEffects(GameEvent $event): void
    {
        [$card, $target] = match (true) {
            $event instanceof CreatureSummoned => [$event->card, $event->target],
            $event instanceof CardPlayed && in_array($event->card->definition->kind, [CardKind::Spell, CardKind::Item], true) => [$event->card, $event->target],
            default => [null, null],
        };
        if ($card === null) {
            return;
        }

        foreach ($this->abilitiesOf($card, EffectAbilityInterface::class) as [$effect, $context]) {
            $effect->apply($context, $target);
        }
    }

    /**
     * Ауры всех источников в игре: существ, построек на точках и ландшафта.
     *
     * @return list<array{AuraAbilityInterface, AbilityContext}>
     */
    private function auras(): array
    {
        $auras = [];
        foreach ($this->sources() as $source) {
            array_push($auras, ...$this->abilitiesOf($source, AuraAbilityInterface::class));
        }

        return $auras;
    }

    /**
     * Всё, что действует на партию: существа в игре, постройки на точках, ландшафты.
     *
     * @return list<CardInstance>
     */
    private function sources(): array
    {
        return [...$this->state->cardsInPlay(), ...$this->state->permanents()];
    }

    /**
     * Чьи способности слышат событие: все карты в игре, постройки на точках, ландшафт
     * и все карты, упомянутые в самом событии — даже если их уже нет в игре:
     * заклинание не выходит на поле, а существо, погибшее в том же бою, ещё должно сработать
     * на свой удар (например, Poison).
     *
     * @return list<CardInstance>
     */
    private function listeners(GameEvent $event): array
    {
        $cards = $this->sources();

        foreach (get_object_vars($event) as $value) {
            if ($value instanceof CardInstance && !in_array($value, $cards, true)) {
                $cards[] = $value;
            }
        }

        return $cards;
    }

    private function removeDead(): void
    {
        foreach ($this->state->cardsInPlay() as $card) {
            if ($this->health($card) <= 0 || ($card->state['destroyed'] ?? false)) {
                $this->state->removeFromPlay($card);
                $this->state->player($card->owner)->graveyard[] = $card;
                $this->emit(new CreatureDied($card));
            }
        }
    }

    private function checkWinner(): void
    {
        if ($this->state->isOver()) {
            return;
        }

        foreach ($this->state->players as $player) {
            if ($player->fortification <= 0) {
                $this->state->winner = $this->state->opponentOf($player->index);
                $this->emit(new GameWon($this->state->winner));

                return;
            }
        }
    }
}
