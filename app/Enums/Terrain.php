<?php

namespace App\Enums;

/**
 * Dónde se juegan los Juegos. La ciudad en ruinas es la arena de siempre;
 * las demás tienen sus propios eventos y catástrofes, y alguna regla que cambia un poco el juego.
 */
enum Terrain: string
{
    case City = 'city';
    case Desert = 'desert';
    case Peaks = 'peaks';
    case Swamp = 'swamp';

    public function label(): string
    {
        return match ($this) {
            self::City => 'La ciudad en ruinas',
            self::Desert => 'El desierto de sal',
            self::Peaks => 'Las cumbres heladas',
            self::Swamp => 'Los pantanos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::City => 'Rascacielos caídos, túneles de metro y coches calcinados. La arena de siempre.',
            self::Desert => 'Dunas, salinas y un sol que no perdona. Hay muy poco que encontrar.',
            self::Peaks => 'Glaciares, ventiscas y refugios abandonados. Con este frío, cuesta menos fiarse de alguien.',
            self::Swamp => 'Agua turbia, niebla y arenas movedizas. Es difícil cruzarse con nadie... hasta que es tarde.',
        };
    }

    /** Lo que dice el anuncio al empezar la partida (en la ciudad no hay: es lo de siempre) */
    public function intro(): ?string
    {
        return match ($this) {
            self::City => null,
            self::Desert => 'Este año, los tributos se elevan sobre un desierto de sal que se extiende hasta el horizonte. Cuidad el agua.',
            self::Peaks => 'Este año, la arena es una cordillera cubierta de hielo. El frío matará a tantos como los demás tributos.',
            self::Swamp => 'Este año, la arena es un pantano inmenso cubierto de niebla. Mirad dónde pisáis.',
        };
    }

    /**
     * Puntos que suma o resta a las decisiones del motor, igual que las personalidades y las reglas.
     * La ciudad no cambia nada: así las partidas de antes salen igual.
     */
    public function modifier(string $decision): int
    {
        $modifiers = match ($this) {
            self::City => [],
            self::Desert => [Personality::DISCOVERY => -15],
            self::Peaks => [Personality::FRIENDSHIP => 15],
            self::Swamp => [Personality::PAIR => -10, Personality::ALLY_ATTACK => 15],
        };

        return $modifiers[$decision] ?? 0;
    }

    /**
     * ¿Se prefieren los eventos propios de la arena? En la ciudad no: allí casi todos los eventos son "propios",
     * y además así se mantiene exactamente el mismo azar que antes de que hubiera arenas.
     */
    public function favoursOwnEvents(): bool
    {
        return $this !== self::City;
    }
}
