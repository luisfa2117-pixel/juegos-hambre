<?php

namespace App\Console\Commands;

use App\Actions\PlayGame;
use App\Models\GameTribute;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Juega una partida con todos los personajes y la guarda en la base de datos.
 * Uso: php artisan game:play
 *      php artisan game:play --seed=42
 */
class PlayGameCommand extends Command
{
    protected $signature = 'game:play {--seed= : Semilla para repetir exactamente una partida} {--email=demo@juegos.test : De quién son los personajes}';

    protected $description = 'Juega una partida con todos los personajes de un usuario y la guarda';

    // Laravel crea el PlayGame y nos lo pasa (inyección de dependencias)
    public function handle(PlayGame $playGame): int
    {
        $user = User::where('email', $this->option('email'))->first();

        if ($user === null) {
            $this->error("No hay ninguna cuenta con el correo {$this->option('email')}.");

            return self::FAILURE;
        }

        $characters = $user->characters()->get();

        if ($characters->count() < 2) {
            $this->error('Hacen falta al menos 2 personajes.');

            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : null;
        $game = $playGame->handle($user, $characters, $seed);

        $this->info("Partida #{$game->id} guardada: {$game->total_rounds} rondas (semilla {$game->seed}).");

        $this->table(
            ['Puesto', 'Tributo', 'Distrito', 'Murió en la ronda'],
            $game->tributes()->with('district')->get()->map(fn (GameTribute $t) => [
                $t->placement,
                $t->name,
                $t->district->name,
                $t->died_in_round ?? '—',
            ]),
        );

        $winner = $game->winner();
        $winner !== null
            ? $this->info("🏆 Ganador: {$winner->name}")
            : $this->warn('No hubo ganador.');

        return self::SUCCESS;
    }
}
