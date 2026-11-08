<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DistrictSeeder::class,
            ItemSeeder::class,
            GameEventSeeder::class,
        ]);

        // La cuenta demo@juegos.test (administradora, contraseña "password") solo en tu ordenador
        // y en los tests. En el servidor sería una puerta abierta para cualquiera.
        if (! app()->isProduction()) {
            $this->call(CharacterSeeder::class);
        }
    }
}
