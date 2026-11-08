<?php

namespace App\Game\Enum;

/**
 * Тип карты в партии — slug из card_types. В v0.1 разыгрываются существа, заклинания и ландшафты.
 */
enum CardKind: string
{
    case Creature = 'creature';
    case Spell = 'spell';
    case Item = 'item';           // предмет: надевается на своё существо (Кинжал, Щит)
    case Action = 'action';
    case Support = 'support';
    case Building = 'building';   // в т.ч. нейтральные постройки на точках сопряжения
    case Landscape = 'landscape'; // общий ландшафт поля: «Солнечный день», «Лес»…
}
