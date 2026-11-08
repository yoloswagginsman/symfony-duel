<?php

namespace App\Game\Ability;

use App\Game\Ability\Contract\TriggeredAbilityInterface;
use App\Game\Event\GameEvent;
use App\Game\Event\TurnStarted;

/**
 * Срок действия ландшафта N, по умолчанию 3: в начале каждого хода разыгравшего — минус один,
 * на N-м ландшафт уходит в сброс. Противник успевает сыграть под ним N ходов.
 * Без этой способности ландшафт держится, пока его не заменят.
 */
final readonly class Duration implements TriggeredAbilityInterface
{
    private const int DEFAULT_TURNS = 3;

    public static function slug(): string
    {
        return 'Duration';
    }

    public function onEvent(GameEvent $event, AbilityContext $context): void
    {
        $landscape = $context->source;
        if (!$event instanceof TurnStarted || $event->player !== $landscape->owner || $context->game->state->sideOf($landscape) === null) {
            return;
        }

        $landscape->state['turns_left'] = ($landscape->state['turns_left'] ?? $context->value(self::DEFAULT_TURNS)) - 1;
        if ($landscape->state['turns_left'] <= 0) {
            $context->game->endLandscape($landscape);
        }
    }
}
