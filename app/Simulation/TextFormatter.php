<?php

namespace App\Simulation;

use App\Enums\Gender;

/**
 * Convierte los textos de los eventos en frases con nombres y género correctos.
 *
 * Marcas que entiende:
 *   {A}            el nombre del participante A              → "Avery"
 *   {A:o/a}        masculino / femenino según A              → "herid{A:o/a}" = "herida"
 *   {AB:os/as}     plural: femenino solo si TODOS son mujeres → "junt{AB:os/as}" = "juntas"
 *
 * Es la regla del español: "Avery y Avril, juntas", pero "Avery y Noah, juntos".
 */
class TextFormatter
{
    private const LABELS = ['A', 'B', 'C', 'D'];

    /** @param Tribute[] $participants En orden: A, B, C, D */
    public static function format(string $text, array $participants): string
    {
        $byLabel = array_combine(
            array_slice(self::LABELS, 0, count($participants)),
            array_values($participants),
        );

        // 1. Las marcas de género: {A:o/a}, {AB:os/as}...
        $text = preg_replace_callback(
            '/\{([A-D]+):([^\/{}]*)\/([^{}]*)\}/u',
            function (array $match) use ($byLabel) {
                [, $labels, $masculine, $feminine] = $match;
                $tributes = array_map(fn (string $label) => $byLabel[$label], str_split($labels));

                return self::gendered($tributes, $masculine, $feminine);
            },
            $text,
        );

        // 2. Los nombres: {A}, {B}...
        foreach ($byLabel as $label => $tribute) {
            $text = str_replace('{' . $label . '}', $tribute->name, $text);
        }

        return $text;
    }

    /**
     * Elige la forma femenina solo si todos los tributos son mujeres.
     *
     * @param Tribute[] $tributes
     */
    public static function gendered(array $tributes, string $masculine, string $feminine): string
    {
        foreach ($tributes as $tribute) {
            if ($tribute->gender !== Gender::Female) {
                return $masculine;
            }
        }

        return $feminine;
    }

    /**
     * ["Dean"] → "Dean" · ["Dean", "Avery"] → "Dean y Avery" · [...3] → "Dean, Avery y Noah"
     *
     * @param Tribute[] $tributes
     */
    public static function names(array $tributes): string
    {
        $names = array_map(fn (Tribute $t) => $t->name, $tributes);
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names) . ' y ' . $last;
    }
}
