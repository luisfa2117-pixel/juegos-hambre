<?php

namespace App\Actions;

use App\Models\User;

/**
 * Crea una cuenta de invitado: sin correo ni contraseña, con el reparto de serie.
 * Es lo que hay detrás del botón "Probar sin registrarse".
 */
class CreateGuestAccount
{
    public function __construct(private readonly CreateStarterCharacters $createStarterCharacters) {}

    public function handle(): User
    {
        $user = new User;
        $user->name = 'Invitado '.random_int(1000, 9999);
        $user->is_guest = true;
        $user->last_seen_at = now();
        $user->save();

        $this->createStarterCharacters->handle($user);

        return $user;
    }
}
