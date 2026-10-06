<?php

namespace App\Game;

/**
 * Числа правил партии (v0.1) — в одном месте. Описание правил: docs/game-rules.md.
 */
final class GameRules
{
    public const int FORTIFICATION = 30;      // здоровье фортификации
    public const int MAX_MANA = 9;            // максимум маны
    public const int BOARD_SIZE = 4;          // ячеек существ у игрока
    public const int CAPTURE_POINTS = 2;      // общих точек сопряжения — на каждой случайная нейтральная постройка
    public const int NEUTRAL = -1;            // «владелец» нейтральной постройки
    public const array START_HAND = [3, 4];   // стартовая рука: первый игрок, второй (+1 за ход вторым)

    // Колода: ровно DECK_SIZE карт, одной карты — не больше MAX_COPIES, легендарной — MAX_LEGENDARY_COPIES
    public const int DECK_SIZE = 20;
    public const int MAX_COPIES = 3;
    public const int MAX_LEGENDARY_COPIES = 1;
}
