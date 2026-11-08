<?php

namespace App\Simulation;

use App\Enums\GameRule;

/** Las reglas especiales activas en una partida (puede no haber ninguna) */
class GameRules
{
    /** @var GameRule[] */
    private array $rules;

    /** @param GameRule[] $rules */
    public function __construct(array $rules = [])
    {
        // Siempre en el mismo orden (el del enum), las elija quien las elija
        $this->rules = array_values(array_filter(
            GameRule::cases(),
            fn (GameRule $rule) => in_array($rule, $rules, true),
        ));
    }

    /**
     * A partir de lo que se guarda en la base de datos: ['armed', 'half_life']
     *
     * @param  array<int, string>|null  $values
     */
    public static function fromValues(?array $values): self
    {
        return new self(array_values(array_filter(array_map(
            fn (string $value) => GameRule::tryFrom($value),
            $values ?? [],
        ))));
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    public function has(GameRule $rule): bool
    {
        return in_array($rule, $this->rules, true);
    }

    /** Lo que suman todas las reglas activas a una decisión del motor */
    public function modifier(string $decision): int
    {
        return array_sum(array_map(fn (GameRule $rule) => $rule->modifier($decision), $this->rules));
    }

    /** @return GameRule[] */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * El aviso con el que empieza la partida, como si fuera una catástrofe más.
     * $quell: si las ha elegido el Vasallaje de los 25.
     */
    public function announcement(bool $quell = false): ?EventResult
    {
        if ($this->rules === []) {
            return null;
        }

        $descriptions = implode(' ', array_map(fn (GameRule $rule) => $rule->description(), $this->rules));

        return new EventResult(
            text: $quell
                ? "Para celebrar el Vasallaje de los 25, el Capitolio cambia las reglas de este año. {$descriptions}"
                : $descriptions,
            arena: $quell ? 'Vasallaje de los 25' : 'Reglas especiales',
        );
    }

    /** @return string[] Para guardar en la base de datos */
    public function values(): array
    {
        return array_map(fn (GameRule $rule) => $rule->value, $this->rules);
    }
}
