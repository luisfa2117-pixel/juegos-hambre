<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Models\Character;
use App\Models\District;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    /** El reparto de tu Main.java */
    public function run(): void
    {
        $districts = District::pluck('id', 'slug');   // ['costa_este' => 1, ...]

        $characters = [
            ['Dean',     Gender::Male,   'cinturon_oxidado'],
            ['Avery',    Gender::Female, 'costa_este'],
            ['De Santi', Gender::Male,   'norte_helado'],
            ['Hannah',   Gender::Female, 'sur_quemado'],
            ['Desiree',  Gender::Female, 'cinturon_oxidado'],
            ['Noah',     Gender::Male,   'pacifico_tecno'],
            ['Avril',    Gender::Female, 'costa_este'],
            ['Horacio',  Gender::Male,   'norte_helado'],
        ];

        foreach ($characters as [$name, $gender, $district]) {
            Character::updateOrCreate(
                ['name' => $name],
                ['gender' => $gender, 'district_id' => $districts[$district]],
            );
        }
    }
}
