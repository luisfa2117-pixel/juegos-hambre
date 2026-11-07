<?php

namespace App\Enums;

/**
 * La personalidad de un tributo: cambia sus probabilidades en la partida.
 * Un personaje sin personalidad (null) es "equilibrado" y juega con las probabilidades normales.
 */
enum Personality: string
{
    case Aggressive = 'aggressive';
    case Coward = 'coward';
    case Loyal = 'loyal';
    case Treacherous = 'treacherous';
    case Resourceful = 'resourceful';
    case Sociable = 'sociable';

    // Las decisiones del motor que una personalidad puede cambiar
    public const PAIR = 'pair';                // que le toque un evento de pareja
    public const FRIENDSHIP = 'friendship';    // hacerse aliado de un desconocido
    public const BETRAYAL = 'betrayal';        // traicionar a un aliado
    public const WEAPON = 'weapon';            // usar su arma en una pelea
    public const DISCOVERY = 'discovery';      // encontrar un objeto
    public const ALLY_ATTACK = 'ally_attack';  // atacar junto a un aliado

    /**
     * Puntos que suma o resta a la probabilidad normal de cada decisión.
     * Por ejemplo, un traicionero traiciona con un 35 % + 35 = 70 %.
     */
    public function modifier(string $decision): int
    {
        $modifiers = match ($this) {
            self::Aggressive => [self::PAIR => 5, self::FRIENDSHIP => -10, self::BETRAYAL => 10, self::WEAPON => 25, self::ALLY_ATTACK => 25],
            self::Coward => [self::PAIR => -20, self::FRIENDSHIP => 15, self::WEAPON => -30, self::ALLY_ATTACK => -20],
            self::Loyal => [self::FRIENDSHIP => 10, self::BETRAYAL => -100],
            self::Treacherous => [self::FRIENDSHIP => 15, self::BETRAYAL => 35],
            self::Resourceful => [self::DISCOVERY => 10],
            self::Sociable => [self::FRIENDSHIP => 30, self::ALLY_ATTACK => 10],
        };

        return $modifiers[$decision] ?? 0;
    }

    /** "Agresivo" o "Agresiva" según el género; sin género, "Agresivo/a" (para los formularios) */
    public function label(?Gender $gender = null): string
    {
        [$masculine, $feminine] = match ($this) {
            self::Aggressive => ['Agresivo', 'Agresiva'],
            self::Coward => ['Cobarde', 'Cobarde'],
            self::Loyal => ['Leal', 'Leal'],
            self::Treacherous => ['Traicionero', 'Traicionera'],
            self::Resourceful => ['Ingenioso', 'Ingeniosa'],
            self::Sociable => ['Sociable', 'Sociable'],
        };

        return match (true) {
            $gender === Gender::Male || $masculine === $feminine => $masculine,
            $gender === Gender::Female => $feminine,
            default => "{$masculine}/" . mb_substr($feminine, -1),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Aggressive => 'Busca pelea: pelea más, se alía menos y usa sus armas siempre que puede.',
            self::Coward => 'Evita los enfrentamientos y busca protección en otros.',
            self::Loyal => 'Nunca traiciona a sus aliados.',
            self::Treacherous => 'Hace aliados con facilidad... y los traiciona en cuanto puede.',
            self::Resourceful => 'Encuentra objetos útiles donde los demás solo ven basura.',
            self::Sociable => 'Hace amigos con facilidad y prefiere atacar en equipo.',
        };
    }
}
