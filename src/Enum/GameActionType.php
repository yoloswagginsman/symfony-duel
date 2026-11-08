<?php

namespace App\Enum;

/**
 * Ход игрока в API (POST /api/games/{id}/actions, поле type).
 */
enum GameActionType: string
{
    case PLAY_CARD = 'play_card';           // cardId, cell (для существа), targetId
    case ATTACK = 'attack';                 // attackerId, targetId (без цели — фортификация)
    case CAPTURE_POINT = 'capture_point';   // creatureId, point
    case END_TURN = 'end_turn';
    case SURRENDER = 'surrender';           // можно и в чужой ход
}
