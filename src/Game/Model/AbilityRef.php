<?php

namespace App\Game\Model;

/**
 * Способность на карте: slug (класс способности ищется по нему) и число, если есть («Heal 2»).
 */
final readonly class AbilityRef
{
    public function __construct(
        public string $slug,
        public ?int $value = null,
    ) {
    }

    /**
     * @return array{slug: string, value: ?int}
     */
    public function toArray(): array
    {
        return ['slug' => $this->slug, 'value' => $this->value];
    }

    /**
     * @param array{slug: string, value: ?int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['slug'], $data['value']);
    }
}
