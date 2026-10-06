<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\StatModifierInterface;
use App\Game\Model\CardInstance;

/**
 * Вожак стаи N (по умолчанию 1) — пример легендарной способности, зависящей от поля:
 * +N к атаке за каждое другое своё существо той же расы в игре (на поле и на точках).
 * Бонус пересчитывается всякий раз — союзник погиб, бонус уменьшился.
 */
final readonly class PackLeader implements StatModifierInterface
{
    private const int DEFAULT_BONUS = 1;

    public static function slug(): string
    {
        return 'Pack_Leader';
    }

    public function attackBonus(AbilityContext $context): int
    {
        $leader = $context->source;
        $pack = array_filter(
            $context->game->state->cardsInPlay($leader->owner),
            static fn (CardInstance $card) => $card !== $leader
                && $leader->definition->race !== null
                && $card->definition->race === $leader->definition->race,
        );

        return count($pack) * $context->value(self::DEFAULT_BONUS);
    }

    public function healthBonus(AbilityContext $context): int
    {
        return 0;
    }
}
