<?php

namespace App\Game\Engine;

/**
 * Действие нарушает правила (не твой ход, не хватает маны, мешает Провокация…). Партия не меняется.
 */
final class IllegalActionException extends \DomainException
{
}
