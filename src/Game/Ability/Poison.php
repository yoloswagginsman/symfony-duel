<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\CreatureDamaged;
use App\Game\Event\GameEvent;

/**
 * Яд — смертельный удар (как Lethal в TES Legends): любой урон от этой карты убивает существо.
 * Щит спасает: поглощённый удар — это не урон, события CreatureDamaged нет.
 */
final readonly class Poison implements TriggeredAbilityInterface
{
    public static function slug(): string
    {
        return 'Poison';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        if ($event instanceof CreatureDamaged && $event->source === $context->source) {
            $context->game->destroy($event->card);
        }
    }
}
