<?php

namespace App\Game\Ability\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Способность карты — класс на каждый slug из data/content/abilities.yaml.
 * Что способность умеет, задают интерфейсы, которые она реализует (их можно сочетать):
 * TriggeredAbilityInterface, StatModifierInterface, KeywordAbilityInterface, DamagePreventionInterface.
 *
 * Классы без состояния: состояние конкретной карты — в CardInstance::$state.
 */
#[AutoconfigureTag]
interface AbilityInterface
{
    /**
     * slug способности в контенте (abilities.yaml, поле type) — по нему карта находит класс.
     */
    public static function slug(): string;
}
