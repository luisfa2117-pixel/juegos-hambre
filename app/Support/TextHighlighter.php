<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Pone en negrita los nombres de los tributos dentro de un texto.
 *
 * Primero escapa el texto con e() y DESPUÉS añade las etiquetas <strong>.
 * Si un personaje se llamara "<script>...", se mostraría como texto y no se ejecutaría.
 */
class TextHighlighter
{
    /** @param iterable<string> $names */
    public static function names(string $text, iterable $names): HtmlString
    {
        $escaped = e($text);
        $names = collect($names)->filter()->unique()
            ->sortByDesc(fn (string $name) => mb_strlen($name))   // "Avery Jr" antes que "Avery"
            ->map(fn (string $name) => preg_quote(e($name), '/'));

        if ($names->isEmpty()) {
            return new HtmlString($escaped);
        }

        $pattern = '/('.$names->implode('|').')/u';

        return new HtmlString(preg_replace($pattern, '<strong class="font-semibold text-ash">$1</strong>', $escaped));
    }
}
