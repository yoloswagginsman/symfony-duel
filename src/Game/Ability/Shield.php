<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\DamagePreventionInterface;

/**
 * Щит: полностью поглощает первый урон по существу и ломается.
 */
final readonly class Shield implements DamagePreventionInterface
{
    public static function slug(): string
    {
        return 'Shield';
    }

    public function absorb(int $damage, AbilityContext $context): int
    {
        if ($damage <= 0 || ($context->source->state['shield_broken'] ?? false)) {
            return $damage;
        }

        $context->source->state['shield_broken'] = true;

        return 0;
    }
}
