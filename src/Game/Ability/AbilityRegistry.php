<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\AbilityInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Все классы способностей по slug. Способность из контента без класса пока ни на что не влияет.
 */
final readonly class AbilityRegistry
{
    /** @var array<string, AbilityInterface> */
    private array $abilities;

    /**
     * @param iterable<AbilityInterface> $abilities
     */
    public function __construct(
        #[AutowireIterator(AbilityInterface::class)]
        iterable $abilities,
    ) {
        $bySlug = [];
        foreach ($abilities as $ability) {
            $bySlug[$ability::slug()] = $ability;
        }
        $this->abilities = $bySlug;
    }

    public function get(string $slug): ?AbilityInterface
    {
        return $this->abilities[$slug] ?? null;
    }
}
