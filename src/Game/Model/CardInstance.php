<?php

namespace App\Game\Model;

/**
 * Конкретная карта в партии: у двух одинаковых карт в колоде — разные экземпляры.
 * Атаку и здоровье с учётом способностей считает GameContext::attack()/health().
 */
final class CardInstance
{
    public int $damage = 0;
    public bool $summonedThisTurn = false;
    public bool $actedThisTurn = false;

    /**
     * Состояние способностей этого экземпляра (например, ['shield_broken' => true, 'frenzy' => 2]).
     *
     * @var array<string, mixed>
     */
    public array $state = [];

    public function __construct(
        public readonly int $id,
        public readonly CardDefinition $definition,
        public readonly int $owner,
    ) {
    }

    /**
     * Карта сохраняется вместе с описанием — партия не зависит от последующих правок контента.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner' => $this->owner,
            'definition' => $this->definition->toArray(),
            'damage' => $this->damage,
            'summonedThisTurn' => $this->summonedThisTurn,
            'actedThisTurn' => $this->actedThisTurn,
            'state' => $this->state,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $card = new self($data['id'], CardDefinition::fromArray($data['definition']), $data['owner']);
        $card->damage = $data['damage'];
        $card->summonedThisTurn = $data['summonedThisTurn'];
        $card->actedThisTurn = $data['actedThisTurn'];
        $card->state = $data['state'];

        return $card;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromNullableArray(?array $data): ?self
    {
        return $data !== null ? self::fromArray($data) : null;
    }
}
