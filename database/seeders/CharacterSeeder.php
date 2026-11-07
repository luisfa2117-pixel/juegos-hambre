<?php

namespace Database\Seeders;

use App\Actions\CreateStarterCharacters;
use App\Models\User;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    /** El usuario de pruebas con el que entras en local (y que usan los tests) */
    public const DEMO_EMAIL = 'demo@juegos.test';

    public function run(CreateStarterCharacters $createStarterCharacters): void
    {
        $demo = User::firstOrNew(['email' => self::DEMO_EMAIL]);
        $demo->name = 'Demo';
        $demo->password = 'password';
        // is_admin no se puede rellenar con fill() (ver User::$fillable): se asigna a mano
        $demo->is_admin = true;
        $demo->save();

        $createStarterCharacters->handle($demo);
    }
}
