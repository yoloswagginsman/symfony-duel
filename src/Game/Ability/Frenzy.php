<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\StatModifierInterface;
use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\CreatureDamaged;
use App\Game\Event\GameEvent;

/**
 * Ярость N (по умолчанию 1): каждый раз, получив урон и выжив, существо получает +N к атаке.
 * Пример способности сразу двух видов: реагирует на событие и меняет характеристику.
 */
final readonly class Frenzy implements TriggeredAbilityInterface, StatModifierInterface
{
    private const int DEFAULT_BONUS = 1;

    public static function slug(): string
    {
        return 'Frenzy';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        if ($event instanceof CreatureDamaged && $event->card === $context->source) {
            $state = &$context->source->state;
            $state['frenzy'] = ($state['frenzy'] ?? 0) + $context->value(self::DEFAULT_BONUS);
        }
    }

    public function attackBonus(AbilityContext $context): int
    {
        return $context->source->state['frenzy'] ?? 0;
    }

    public function healthBonus(AbilityContext $context): int
    {
        return 0;
    }
}
