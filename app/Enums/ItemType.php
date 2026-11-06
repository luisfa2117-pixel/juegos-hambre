<?php

namespace App\Enums;

enum ItemType: string
{
    case Weapon = 'weapon';
    case Armor  = 'armor';
    case Heal   = 'heal';
    case Energy = 'energy';
}
