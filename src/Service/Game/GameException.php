<?php

namespace App\Service\Game;

/**
 * Партию нельзя создать или продолжить так, как просят: она уже идёт, это ваша же партия,
 * чужая колода… Правила хода — отдельно, IllegalActionException.
 */
final class GameException extends \DomainException
{
}
