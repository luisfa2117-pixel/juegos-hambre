<?php

namespace App\Enums;

enum EventCategory: string
{
    case Solo      = 'solo';       // 1 tributo
    case Duo       = 'duo';        // 2 desconocidos que se pelean
    case Trio      = 'trio';       // 3 tributos
    case Quartet   = 'quartet';    // 4 tributos
    case Ally      = 'ally';       // 2 tributos que ya son aliados
    case Befriend  = 'befriend';   // 2 desconocidos que se hacen aliados
    case Discovery = 'discovery';  // 1 tributo encuentra un objeto
    case Weapon    = 'weapon';     // ataque que requiere un arma concreta
}
