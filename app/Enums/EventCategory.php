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
    case Betrayal  = 'betrayal';   // un aliado traiciona al otro

    /** Cómo se muestra en la web */
    public function label(): string
    {
        return match ($this) {
            self::Solo => 'Individual',
            self::Discovery => 'Descubrimiento',
            self::Duo => 'Pelea entre dos',
            self::Weapon => 'Ataque con arma',
            self::Trio => 'Grupo de tres',
            self::Quartet => 'Grupo de cuatro',
            self::Befriend => 'Nueva alianza',
            self::Ally => 'Entre aliados',
            self::Betrayal => 'Traición',
        };
    }

    /** Cuántos tributos participan en los eventos de este tipo */
    public function participants(): int
    {
        return match ($this) {
            self::Solo, self::Discovery => 1,
            self::Duo, self::Weapon, self::Befriend, self::Ally, self::Betrayal => 2,
            self::Trio => 3,
            self::Quartet => 4,
        };
    }
}
