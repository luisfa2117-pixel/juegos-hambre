<?php

namespace App\Enums;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Reglas especiales que se pueden activar al crear una partida.
 * Igual que las personalidades, pueden cambiar las probabilidades de las decisiones del motor,
 * pero afectan a toda la partida, no a un tributo.
 */
enum GameRule: string
{
    case NoAlliances = 'no_alliances';
    case Traitors = 'traitors';
    case Armed = 'armed';
    case HalfLife = 'half_life';
    case Catastrophes = 'catastrophes';
    case Scarcity = 'scarcity';

    public function label(): string
    {
        return match ($this) {
            self::NoAlliances => 'Cada uno por su cuenta',
            self::Traitors => 'Juego de traidores',
            self::Armed => 'Armados hasta los dientes',
            self::HalfLife => 'Al límite',
            self::Catastrophes => 'Los Vigilantes se aburren',
            self::Scarcity => 'Escasez',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoAlliances => 'No hay alianzas: todos contra todos.',
            self::Traitors => 'Aliarse es fácil... y las traiciones, muchísimo más frecuentes.',
            self::Armed => 'Cada tributo empieza la partida con un arma al azar.',
            self::HalfLife => 'Todos empiezan con media vida.',
            self::Catastrophes => 'Catástrofes desde el Día 1, y mucho más a menudo.',
            self::Scarcity => 'No se encuentra ningún objeto: solo lo que manden los patrocinadores.',
        };
    }

    /**
     * Puntos que suma o resta a las decisiones del motor (las mismas que las personalidades).
     * El -100 significa "nunca".
     */
    public function modifier(string $decision): int
    {
        $modifiers = match ($this) {
            self::NoAlliances => [Personality::FRIENDSHIP => -100],
            self::Traitors => [Personality::FRIENDSHIP => 25, Personality::BETRAYAL => 40],
            self::Scarcity => [Personality::DISCOVERY => -100],
            default => [],
        };

        return $modifiers[$decision] ?? 0;
    }

    /** ¿Tiene sentido junto a $other? Sin alianzas no hay a quién traicionar */
    public function conflictsWith(self $other): bool
    {
        $pair = [$this, $other];

        return in_array(self::NoAlliances, $pair, true) && in_array(self::Traitors, $pair, true);
    }

    /**
     * El Vasallaje de los 25: dos reglas compatibles elegidas al azar a partir de la semilla.
     * Con la misma semilla salen siempre las mismas, así se puede repetir la partida.
     *
     * @return self[]
     */
    public static function quell(int $seed): array
    {
        $random = new Randomizer(new Xoshiro256StarStar(hash('sha256', "{$seed}:vasallaje", true)));
        $shuffled = $random->shuffleArray(self::cases());

        $first = $shuffled[0];
        foreach (array_slice($shuffled, 1) as $candidate) {
            if (! $first->conflictsWith($candidate)) {
                return [$first, $candidate];
            }
        }

        return [$first];
    }
}
