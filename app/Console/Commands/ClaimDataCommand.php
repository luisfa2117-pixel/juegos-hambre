<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Pasa a un usuario los personajes y partidas sin dueño (los creados antes de que hubiera login).
 * Uso: php artisan user:claim tu@correo.com --admin
 */
class ClaimDataCommand extends Command
{
    protected $signature = 'user:claim {email : El correo de la cuenta} {--admin : Además, hacerle administrador}';

    protected $description = 'Asigna a un usuario los personajes y partidas que no tienen dueño';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No hay ninguna cuenta con ese correo. Regístrate primero en la web.');

            return self::FAILURE;
        }

        // Si ya tiene un personaje con el mismo nombre (los 8 del registro), nos quedamos con el antiguo:
        // tiene su historial de partidas y su foto
        $orphanNames = Character::whereNull('user_id')->pluck('name');
        $deleted = $user->characters()->whereIn('name', $orphanNames)->doesntHave('gameTributes')->delete();

        $characters = Character::whereNull('user_id')->update(['user_id' => $user->id]);
        $games = Game::whereNull('user_id')->update(['user_id' => $user->id]);

        if ($this->option('admin')) {
            $user->is_admin = true;
            $user->save();
        }

        $this->info("{$user->name} recibe {$characters} personajes y {$games} partidas.");

        if ($deleted > 0) {
            $this->line("Se han quitado {$deleted} personajes nuevos que se llamaban igual que los tuyos antiguos.");
        }

        if ($this->option('admin')) {
            $this->info('Ahora es administrador: puede editar los eventos.');
        }

        return self::SUCCESS;
    }
}
