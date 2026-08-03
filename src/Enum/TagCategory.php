<?php

namespace App\Enum;

enum TagCategory: string
{
    case RACE = 'race';
    case ELEMENT = 'element';
    case MECHANIC = 'mechanic';
    case RARITY = 'rarity';
    case TYPE = 'type';
    case SET = 'set';
    case STATUS = 'status';
}
