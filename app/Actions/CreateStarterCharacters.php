<?php

namespace App\Actions;

use App\Enums\Gender;
use App\Models\District;
use App\Models\User;

/**
 * Da a un usuario los 8 personajes de tu Main.java, para que pueda jugar nada más registrarse.
 * También lo usa el CharacterSeeder.
 */
class CreateStarterCharacters
{
    private const CAST = [
        ['Dean',     Gender::Male,   'cinturon_oxidado'],
        ['Avery',    Gender::Female, 'costa_este'],
        ['De Santi', Gender::Male,   'norte_helado'],
        ['Hannah',   Gender::Female, 'sur_quemado'],
        ['Desiree',  Gender::Female, 'cinturon_oxidado'],
        ['Noah',     Gender::Male,   'pacifico_tecno'],
        ['Avril',    Gender::Female, 'costa_este'],
        ['Horacio',  Gender::Male,   'norte_helado'],
    ];

    public function handle(User $user): void
    {
        $districts = District::pluck('id', 'slug');

        foreach (self::CAST as [$name, $gender, $district]) {
            $user->characters()->updateOrCreate(
                ['name' => $name],
                ['gender' => $gender, 'district_id' => $districts[$district]],
            );
        }
    }
}
