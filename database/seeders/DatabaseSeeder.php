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
            CharacterSeeder::class,   // crea también el usuario demo@juegos.test
        ]);
    }
}
