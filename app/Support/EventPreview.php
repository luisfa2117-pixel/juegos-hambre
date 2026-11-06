<?php

namespace App\Support;

use App\Enums\Gender;
use App\Models\District;
use App\Models\GameEvent;
use App\Simulation\TextFormatter;
use App\Simulation\Tribute;

/**
 * Muestra cómo quedará el texto de un evento en una partida, con tributos de ejemplo.
 * Usa el mismo TextFormatter que el motor: lo que ves aquí es lo que saldrá jugando.
 */
class EventPreview
{
    private const MEN = ['Dean', 'Noah', 'Horacio', 'De Santi'];
    private const WOMEN = ['Avery', 'Hannah', 'Avril', 'Desiree'];

    /** @return array{men: string, women: string} */
    public static function both(string $text): array
    {
        return [
            'men' => TextFormatter::format($text, self::tributes(self::MEN, Gender::Male)),
            'women' => TextFormatter::format($text, self::tributes(self::WOMEN, Gender::Female)),
        ];
    }

    /** Con chicas y chicos alternados: Avery, Dean, Hannah, Noah */
    public static function mixed(string $text): string
    {
        $tributes = [
            self::tribute('Avery', Gender::Female),
            self::tribute('Dean', Gender::Male),
            self::tribute('Hannah', Gender::Female),
            self::tribute('Noah', Gender::Male),
        ];

        return TextFormatter::format($text, $tributes);
    }

    /** "A: -4 vida, -2 energía. B: -8 vida." */
    public static function effects(GameEvent $event): string
    {
        $parts = [];
        $labels = array_slice(['A', 'B', 'C', 'D'], 0, $event->participants);
        $actorLabels = array_slice($labels, 0, $event->category->actors());
        $targetLabels = array_slice($labels, $event->category->actors());

        $actor = self::changes($event->actor_hp, $event->actor_energy);

        if ($actor !== null) {
            $parts[] = self::join($actorLabels) . ": {$actor}.";
        }

        $targets = self::changes($event->target_hp, $event->target_energy);

        if ($targets !== null && $targetLabels !== []) {
            $parts[] = self::join($targetLabels) . ": {$targets}.";
        }

        return $parts === [] ? 'Sin efectos en vida ni energía.' : implode(' ', $parts);
    }

    /** ['B', 'C', 'D'] → "B, C y D" */
    private static function join(array $labels): string
    {
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels) . ' y ' . $last;
    }

    public static function names(): array
    {
        return [...self::MEN, ...self::WOMEN];
    }

    private static function changes(int $hp, int $energy): ?string
    {
        $changes = [];

        if ($hp !== 0) {
            $changes[] = sprintf('%+d vida', $hp);
        }

        if ($energy !== 0) {
            $changes[] = sprintf('%+d energía', $energy);
        }

        return $changes === [] ? null : implode(', ', $changes);
    }

    /** @return Tribute[] */
    private static function tributes(array $names, Gender $gender): array
    {
        return array_map(fn (string $name) => self::tribute($name, $gender), $names);
    }

    private static function tribute(string $name, Gender $gender): Tribute
    {
        // Un distrito en memoria, sin guardar: solo hace falta para crear el Tribute
        $district = new District(['name' => '', 'strength_bonus' => 0, 'energy_bonus' => 0]);

        return new Tribute($name, $gender, $district);
    }
}
