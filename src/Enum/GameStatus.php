<?php

namespace App\Enum;

enum GameStatus: string
{
    case WAITING = 'waiting';     // создана, ждёт второго игрока
    case ACTIVE = 'active';       // идёт
    case FINISHED = 'finished';   // есть победитель
}
