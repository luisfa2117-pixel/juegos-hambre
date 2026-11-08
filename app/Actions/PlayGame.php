<?php

namespace App\Actions;

use App\Enums\GameRule;
use App\Enums\ItemType;
use App\Models\Character;
use App\Models\Game;
use App\Models\Item;
use App\Models\User;
use App\Services\GameRecorder;
use App\Simulation\EventPool;
use App\Simulation\GameRules;
use App\Simulation\Simulation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Empieza una partida nueva con unos personajes, la juega entera y la guarda.
 * La usan el comando game:play y la web.
 */
class PlayGame
{
    // Laravel crea el GameRecorder y nos lo pasa (inyección de dependencias)
    public function __construct(private readonly GameRecorder $recorder) {}

    /**
     * @param  Collection<int, Character>  $characters  Tienen que ser del propio usuario
     * @param  bool  $quell  Vasallaje de los 25: las reglas las elige la semilla (y se ignora $rules)
     */
    public function handle(
        User $user,
        Collection $characters,
        ?int $seed = null,
        GameRules $rules = new GameRules,
        bool $quell = false,
    ): Game {
        if ($characters->count() < 2) {
            throw new InvalidArgumentException('Hacen falta al menos 2 personajes para jugar');
        }

        if ($characters->contains(fn (Character $character) => $character->user_id !== $user->id)) {
            throw new InvalidArgumentException('Solo puedes jugar con tus propios personajes');
        }

        // Ordenados por id: así, con los mismos personajes y la misma semilla,
        // la partida sale igual aunque nos los pasen en otro orden
        $characters = $characters->sortBy('id')->values();
        $characters->loadMissing('district');
        $seed ??= random_int(1, 999_999);

        if ($quell) {
            $rules = new GameRules(GameRule::quell($seed));
        }

        $tributes = $characters->map(fn (Character $character) => $character->toTribute())->all();
        $simulation = new Simulation($tributes, EventPool::fromDatabase(), $seed, $rules);
        $simulation->applyStartingRules(Item::where('type', ItemType::Weapon)->orderBy('id')->get()->all());

        if ($announcement = $rules->announcement($quell)) {
            $simulation->announce($announcement);
        }

        // Si algo falla a mitad, la transacción deshace lo guardado y no queda una partida a medias
        return DB::transaction(function () use ($user, $seed, $rules, $quell, $characters, $simulation) {
            $game = $user->games()->create([
                'seed' => $seed,
                'total_rounds' => 0,
                'rules' => $rules->values(),
                'quell' => $quell,
            ]);

            // Copia de cómo era cada personaje; el resultado (puesto, muertes...) lo rellena el GameRecorder
            foreach ($characters as $character) {
                $game->tributes()->create([
                    'character_id' => $character->id,
                    'name' => $character->name,
                    'gender' => $character->gender,
                    'district_id' => $character->district_id,
                    'placement' => 0,
                ]);
            }

            $this->recorder->playOut($game, $simulation);

            return $game->refresh();
        });
    }
}
