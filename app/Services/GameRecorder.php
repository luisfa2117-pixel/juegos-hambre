<?php

namespace App\Services;

use App\Models\Game;
use App\Simulation\EventResult;
use App\Simulation\Simulation;
use App\Simulation\Tribute;

/**
 * Juega una partida hasta el final y la guarda ronda a ronda.
 * Lo usan PlayGame (partida nueva) y ResumeGame (seguir desde una ronda guardada).
 */
class GameRecorder
{
    /** Por si algún día los eventos quedan tan suaves que nadie muere */
    public const MAX_ROUNDS = 100;

    /** Juega desde donde esté la simulación hasta que termine, guardando cada ronda */
    public function playOut(Game $game, Simulation $simulation): void
    {
        while (! $simulation->isOver() && $simulation->round() < self::MAX_ROUNDS) {
            $results = $simulation->runRound();

            $game->rounds()->create([
                'number' => $simulation->round(),
                'events' => array_map(fn (EventResult $result) => $result->toArray(), $results),
                'tributes' => array_map(fn (Tribute $tribute) => $tribute->toArray(), $simulation->tributes()),
                'state' => $simulation->toState(),
            ]);
        }

        $this->summarize($game);
    }

    /**
     * Calcula el resultado de cada tributo (cuándo murió, puesto, asesinatos) a partir de las rondas guardadas.
     * Las rondas son la única fuente de verdad: si se vuelven a jugar, el resumen se recalcula.
     */
    public function summarize(Game $game): void
    {
        $rounds = $game->rounds()->orderBy('number')->get();
        $diedInRound = [];   // ['Avery' => 3, ...]
        $kills = [];         // ['Dean' => 2, ...]
        $killedBy = [];      // ['Avery' => 'Dean', ...]

        foreach ($rounds as $round) {
            foreach ($round->tributes as $tribute) {
                if (! $tribute['alive'] && ! isset($diedInRound[$tribute['name']])) {
                    $diedInRound[$tribute['name']] = $round->number;
                }
            }

            foreach ($round->events as $event) {
                foreach ($event['kills'] ?? [] as $kill) {
                    if ($kill['killers'] !== []) {
                        $killedBy[$kill['victim']] = implode(' y ', $kill['killers']);
                    }

                    foreach ($kill['killers'] as $killer) {
                        $kills[$killer] = ($kills[$killer] ?? 0) + 1;
                    }
                }
            }
        }

        $game->update(['total_rounds' => $rounds->count()]);
        $tributes = $game->tributes()->get();

        foreach ($tributes as $tribute) {
            $died = $diedInRound[$tribute->name] ?? null;

            $tribute->update([
                'died_in_round' => $died,
                'placement' => $this->placement($died, $diedInRound, $tributes->count()),
                'kills' => $kills[$tribute->name] ?? 0,
                'killed_by' => $killedBy[$tribute->name] ?? null,
            ]);
        }
    }

    /**
     * Puesto final: 1 + cuántos aguantaron más que él.
     * Los que mueren en la misma ronda empatan en el puesto.
     *
     * @param  array<string, int>  $diedInRound
     */
    private function placement(?int $died, array $diedInRound, int $total): int
    {
        if ($died === null) {
            return 1;   // sobrevivió
        }

        $survivors = $total - count($diedInRound);
        $diedLater = count(array_filter($diedInRound, fn (int $round) => $round > $died));

        return 1 + $survivors + $diedLater;
    }
}
