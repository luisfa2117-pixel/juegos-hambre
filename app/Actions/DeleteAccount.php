<?php

namespace App\Actions;

use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Borra una cuenta con todo lo suyo: personajes (y sus fotos), partidas y regalos */
class DeleteAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Los personajes, uno a uno con Eloquent: así salta el evento que borra su foto del disco.
            // Si dejáramos que lo hiciera Postgres (cascadeOnDelete), las fotos se quedarían huérfanas.
            $user->characters()->each(fn (Character $character) => $character->delete());

            // Las partidas se van con el usuario (cascadeOnDelete en la base de datos)
            $user->delete();
        });
    }
}
