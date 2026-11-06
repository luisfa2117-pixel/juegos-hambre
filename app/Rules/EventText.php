<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Comprueba que el texto de un evento use bien las marcas:
 * - que estén {A}, {B}... tantos como participantes tiene el tipo de evento,
 * - que no use participantes que no existen ({C} en un evento de dos),
 * - que las marcas de género estén bien escritas ({A:o/a}, {AB:os/as}).
 */
class EventText implements ValidationRule
{
    private const LABELS = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly int $participants)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (substr_count($value, '{') !== substr_count($value, '}')) {
            $fail('Hay una llave { o } sin pareja.');

            return;
        }

        $allowed = array_slice(self::LABELS, 0, $this->participants);
        preg_match_all('/\{([^{}]*)\}/u', $value, $matches);

        foreach ($matches[1] as $inside) {
            if (preg_match('/^[A-D]$/', $inside)) {
                $used = [$inside];
            } elseif (preg_match('/^([A-D]+):[^\/]*\/.*$/u', $inside, $marker)) {
                $used = str_split($marker[1]);
            } else {
                $fail("«{{$inside}}» no es una marca válida. Usa {A} para un nombre o {A:o/a} para el género.");

                return;
            }

            foreach ($used as $label) {
                if (! in_array($label, $allowed, true)) {
                    $fail("El texto usa {{$label}}, pero en este tipo de evento solo participan " . $this->listLabels($allowed) . '.');

                    return;
                }
            }
        }

        foreach ($allowed as $label) {
            if (! str_contains($value, '{' . $label . '}')) {
                $fail('En este tipo de evento participan ' . $this->listLabels($allowed)
                    . ': el texto tiene que incluir el nombre de cada uno.');

                return;
            }
        }
    }

    /** ['A', 'B', 'C'] → "{A}, {B} y {C}" */
    private function listLabels(array $labels): string
    {
        $labels = array_map(fn (string $label) => '{' . $label . '}', $labels);
        $last = array_pop($labels);

        return $labels === [] ? $last : implode(', ', $labels) . ' y ' . $last;
    }
}
