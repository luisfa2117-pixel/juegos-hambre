<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            ['slug' => 'costa_este',       'name' => 'Ruinas de la Costa Este',       'strength_bonus' => 2, 'energy_bonus' => 0],
            ['slug' => 'sur_quemado',      'name' => 'Desiertos del Sur (Texas/NM)',  'strength_bonus' => 0, 'energy_bonus' => 10],
            ['slug' => 'cinturon_oxidado', 'name' => 'El Cinturón Oxidado (Midwest)', 'strength_bonus' => 1, 'energy_bonus' => 5],
            ['slug' => 'norte_helado',     'name' => 'Las Cumbres Nevadas (Norte)',   'strength_bonus' => 3, 'energy_bonus' => -5],
            ['slug' => 'pacifico_tecno',   'name' => 'Federación del Pacífico',       'strength_bonus' => 0, 'energy_bonus' => 5],
            ['slug' => 'pantanos_delta',   'name' => 'Pantanos del Delta (Luisiana)', 'strength_bonus' => 2, 'energy_bonus' => 2],
        ];

        foreach ($districts as $data) {
            District::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
