<?php

namespace App\Enums;

/**
 * Las fases de la partida: la ronda 1 es el baño de sangre en la Cornucopia,
 * y después se alternan día y noche (ronda 2 = Día 1, ronda 3 = Noche 1...).
 */
enum EventPhase: string
{
    case Bloodbath = 'bloodbath';
    case Day = 'day';
    case Night = 'night';

    public static function forRound(int $round): self
    {
        return match (true) {
            $round <= 1 => self::Bloodbath,
            $round % 2 === 0 => self::Day,
            default => self::Night,
        };
    }

    /** "Baño de sangre", "Día 1", "Noche 1", "Día 2"... */
    public static function roundTitle(int $round): string
    {
        return match (self::forRound($round)) {
            self::Bloodbath => 'Baño de sangre',
            self::Day => 'Día ' . intdiv($round, 2),
            self::Night => 'Noche ' . intdiv($round, 2),
        };
    }

    /** "Tras el baño de sangre", "Tras el día 1", "Tras la noche 1" */
    public static function afterRound(int $round): string
    {
        return match (self::forRound($round)) {
            self::Bloodbath => 'Tras el baño de sangre',
            self::Day => 'Tras el día ' . intdiv($round, 2),
            self::Night => 'Tras la noche ' . intdiv($round, 2),
        };
    }

    /** Para el editor de eventos: cuándo puede pasar */
    public function label(): string
    {
        return match ($this) {
            self::Bloodbath => 'Solo en el baño de sangre',
            self::Day => 'Solo de día',
            self::Night => 'Solo de noche',
        };
    }
}
