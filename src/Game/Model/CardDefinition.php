<?php

namespace App\Game\Model;

use App\Game\Enum\CardKind;

/**
 * Карта как она описана в контенте — неизменная. В партии из неё создаются экземпляры (CardInstance).
 */
final readonly class CardDefinition
{
    /**
     * @param list<AbilityRef> $abilities
     */
    public function __construct(
        public string $vendorCode,
        public string $name,
        public CardKind $kind,
        public int $manaCost,
        public ?int $attack = null,
        public ?int $health = null,
        public ?string $race = null,
        public array $abilities = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vendorCode' => $this->vendorCode,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'manaCost' => $this->manaCost,
            'attack' => $this->attack,
            'health' => $this->health,
            'race' => $this->race,
            'abilities' => array_map(static fn (AbilityRef $ref) => $ref->toArray(), $this->abilities),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vendorCode: $data['vendorCode'],
            name: $data['name'],
            kind: CardKind::from($data['kind']),
            manaCost: $data['manaCost'],
            attack: $data['attack'],
            health: $data['health'],
            race: $data['race'],
            abilities: array_map(AbilityRef::fromArray(...), $data['abilities']),
        );
    }
}
