<?php

namespace App\Actions;

use App\Models\District;
use App\Models\Game;
use App\Models\Item;
use App\Services\GameRecorder;
use App\Simulation\EventPool;
use App\Simulation\Simulation;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sigue una partida guardada desde el final de una ronda: lo anterior no cambia
 * y todo lo posterior se vuelve a jugar.
 *
 * Con $change se puede modificar la partida antes de seguir (por ejemplo, para que un
 * patrocinador entregue un regalo). Sin $change, la partida sale exactamente igual que estaba.
 */
class ResumeGame
{
    public function __construct(private readonly GameRecorder $recorder)
    {
    }

    /** @param (Closure(Simulation): void)|null $change */
    public function handle(Game $game, int $afterRound, ?Closure $change = null): Game
    {
        $saved = $game->rounds()->where('number', $afterRound)->first();

        if ($saved === null || $saved->state === null) {
            throw new InvalidArgumentException(
                "No se puede seguir la partida #{$game->id} desde la ronda {$afterRound}: no tiene el estado guardado."
            );
        }

        $simulation = Simulation::fromState(
            $saved->state,
            EventPool::fromDatabase(),
            $game->seed,
            District::all()->keyBy('slug')->all(),
            Item::all()->keyBy('slug')->all(),
        );

        if ($change !== null) {
            $change($simulation);
        }

        return DB::transaction(function () use ($game, $afterRound, $simulation) {
            $game->rounds()->where('number', '>', $afterRound)->delete();
            $this->recorder->playOut($game, $simulation);

            return $game->refresh();
        });
    }
}
