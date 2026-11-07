<?php

namespace App\Actions;

use App\Enums\Gender;
use App\Enums\Personality;
use App\Models\District;
use App\Models\User;

/**
 * Da a un usuario el reparto de serie: un chico y una chica por cada uno de los 6 distritos.
 * También lo usa el CharacterSeeder.
 */
class CreateStarterCharacters
{
    private const CAST = [
        ['Dean',     Gender::Male,   'cinturon_oxidado', Personality::Aggressive],
        ['Desiree',  Gender::Female, 'cinturon_oxidado', Personality::Resourceful],
        ['Marcus',   Gender::Male,   'costa_este',       Personality::Treacherous],
        ['Avery',    Gender::Female, 'costa_este',       Personality::Loyal],
        ['De Santi', Gender::Male,   'norte_helado',     Personality::Aggressive],
        ['Ingrid',   Gender::Female, 'norte_helado',     Personality::Sociable],
        ['Horacio',  Gender::Male,   'sur_quemado',      Personality::Coward],
        ['Hannah',   Gender::Female, 'sur_quemado',      Personality::Resourceful],
        ['Noah',     Gender::Male,   'pacifico_tecno',   Personality::Treacherous],
        ['Avril',    Gender::Female, 'pacifico_tecno',   Personality::Sociable],
        ['Ezra',     Gender::Male,   'pantanos_delta',   Personality::Loyal],
        ['Luz',      Gender::Female, 'pantanos_delta',   Personality::Aggressive],
    ];

    public const COUNT = 12;

    public function handle(User $user): void
    {
        $districts = District::pluck('id', 'slug');

        foreach (self::CAST as [$name, $gender, $district, $personality]) {
            $user->characters()->updateOrCreate(
                ['name' => $name],
                ['gender' => $gender, 'district_id' => $districts[$district], 'personality' => $personality],
            );
        }
    }
}
