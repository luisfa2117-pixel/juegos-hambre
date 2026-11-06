<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\Game;
use App\Simulation\EventPool;
use App\Simulation\EventResult;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Juega una partida completa con el motor y la guarda en la base de datos.
 *
 * Es el puente entre el motor (que no sabe nada de la base de datos)
 * y Eloquent. La usarán el comando game:play y, en la fase 4, la web.
 */
class PlayGame
{
    /** Por si algún día los eventos quedan tan suaves que nadie muere */
    public const MAX_ROUNDS = 100;

    /** @param Collection<int, Character> $characters */
    public function handle(Collection $characters, ?int $seed = null): Game
    {
        if ($characters->count() < 2) {
            throw new InvalidArgumentException('Hacen falta al menos 2 personajes para jugar');
        }

        $characters = $characters->values();
        $characters->loadMissing('district');
        $seed ??= random_int(1, 999_999);

        // Mismo orden que $characters: $tributes[0] es el tributo de $characters[0]
        $tributes = $characters->map(fn (Character $character) => $character->toTribute())->all();

        $simulation = new Simulation($tributes, EventPool::fromDatabase(), new Randomizer(new Mt19937($seed)));

        // 1. Jugar toda la partida en memoria
        $rounds = [];
        $diedInRound = array_fill(0, count($tributes), null);

        while (! $simulation->isOver() && $simulation->round() < self::MAX_ROUNDS) {
            $results = $simulation->runRound();

            foreach ($tributes as $i => $tribute) {
                if (! $tribute->isAlive() && $diedInRound[$i] === null) {
                    $diedInRound[$i] = $simulation->round();
                }
            }

            $rounds[] = [
                'number' => $simulation->round(),
                'events' => array_map(fn (EventResult $result) => $result->toArray(), $results),
                'tributes' => array_map(fn (Tribute $tribute) => $tribute->toArray(), $tributes),
            ];
        }

        // 2. Guardarlo todo. Si algo falla a mitad, la transacción deshace lo guardado
        //    y no queda una partida a medias en la base de datos.
        return DB::transaction(function () use ($seed, $simulation, $characters, $diedInRound, $rounds) {
            $game = Game::create([
                'seed' => $seed,
                'total_rounds' => $simulation->round(),
            ]);

            foreach ($characters as $i => $character) {
                $game->tributes()->create([
                    'character_id' => $character->id,
                    'name' => $character->name,
                    'gender' => $character->gender,
                    'district_id' => $character->district_id,
                    'died_in_round' => $diedInRound[$i],
                    'placement' => $this->placement($i, $diedInRound),
                ]);
            }

            $game->rounds()->createMany($rounds);

            return $game;
        });
    }

    /**
     * Puesto final: 1 + cuántos aguantaron más que él.
     * Los que mueren en la misma ronda empatan en el puesto.
     */
    private function placement(int $index, array $diedInRound): int
    {
        $mine = $diedInRound[$index];

        if ($mine === null) {
            return 1;   // sobrevivió
        }

        $outlivedBy = 0;

        foreach ($diedInRound as $other) {
            if ($other === null || $other > $mine) {
                $outlivedBy++;
            }
        }

        return 1 + $outlivedBy;
    }
}
