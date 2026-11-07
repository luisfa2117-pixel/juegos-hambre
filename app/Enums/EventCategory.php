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
    case AllyAttack = 'ally_attack'; // dos aliados atacan juntos a un tercero
    case Arena     = 'arena';      // lo que le pasa a un tributo durante una catástrofe

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
            self::AllyAttack => 'Aliados atacan juntos',
            self::Arena => 'Catástrofe',
        };
    }

    /** Cuántos tributos participan en los eventos de este tipo */
    public function participants(): int
    {
        return match ($this) {
            self::Solo, self::Discovery, self::Arena => 1,
            self::Duo, self::Weapon, self::Befriend, self::Ally, self::Betrayal => 2,
            self::Trio, self::AllyAttack => 3,
            self::Quartet => 4,
        };
    }

    /**
     * Las que se pueden crear y editar en el editor de la web.
     * Las de catástrofe van unidas a su catástrofe y se definen en el eventos.json.
     *
     * @return self[]
     */
    public static function editable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $case !== self::Arena));
    }

    /**
     * Cuántos de los participantes son "quien actúa" y reciben los efectos de A.
     * Normalmente solo {A}; cuando dos aliados atacan juntos, {A} y {B}.
     */
    public function actors(): int
    {
        return $this === self::AllyAttack ? 2 : 1;
    }
}
