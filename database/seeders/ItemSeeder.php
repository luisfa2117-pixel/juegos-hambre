<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Armas: el poder es orientativo (para ordenarlas); el daño lo pone cada evento
            ['slug' => 'bate_clavos',       'name' => 'Bate con clavos',                  'type' => ItemType::Weapon, 'power' => 4],
            ['slug' => 'taser',             'name' => 'Taser aturdidor',                  'type' => ItemType::Weapon, 'power' => 3],
            ['slug' => 'cuchillo',          'name' => 'Cuchillo de combate',              'type' => ItemType::Weapon, 'power' => 5],
            ['slug' => 'machete',           'name' => 'Machete oxidado',                  'type' => ItemType::Weapon, 'power' => 6],
            ['slug' => 'ballesta',          'name' => 'Ballesta de caza',                 'type' => ItemType::Weapon, 'power' => 7],
            ['slug' => 'pistolaclavos',     'name' => 'Pistola de clavos',                'type' => ItemType::Weapon, 'power' => 8],
            ['slug' => 'granada_pem',       'name' => 'Granada de pulso',                 'type' => ItemType::Weapon, 'power' => 9],
            ['slug' => 'dron_armado',       'name' => 'Dron armado',                      'type' => ItemType::Weapon, 'power' => 9],
            ['slug' => 'pistola',           'name' => 'Pistola sin número de serie',      'type' => ItemType::Weapon, 'power' => 10],
            ['slug' => 'lanzallamas',       'name' => 'Lanzallamas casero',               'type' => ItemType::Weapon, 'power' => 11],
            ['slug' => 'rifle_riel',        'name' => 'Rifle de riel electromagnético',   'type' => ItemType::Weapon, 'power' => 14],

            // Defensas: absorben tanto daño como su poder y se rompen
            ['slug' => 'casco',             'name' => 'Casco antidisturbios',             'type' => ItemType::Armor,  'power' => 3],
            ['slug' => 'chaleco',           'name' => 'Chaleco antibalas',                'type' => ItemType::Armor,  'power' => 5],
            ['slug' => 'escudo_policial',   'name' => 'Escudo antidisturbios',            'type' => ItemType::Armor,  'power' => 6],
            ['slug' => 'escudo',            'name' => 'Generador de escudo personal',     'type' => ItemType::Armor,  'power' => 8],
            ['slug' => 'exotraje',          'name' => 'Exoesqueleto blindado',            'type' => ItemType::Armor,  'power' => 10],

            // Curación: vida que recuperan
            ['slug' => 'vendas',            'name' => 'Vendas y desinfectante',           'type' => ItemType::Heal,   'power' => 5],
            ['slug' => 'botiquin',          'name' => 'Botiquín médico',                  'type' => ItemType::Heal,   'power' => 10],
            ['slug' => 'nanobots',          'name' => 'Inyector de nanobots',             'type' => ItemType::Heal,   'power' => 15],

            // Energía: energía que recuperan
            ['slug' => 'barritas',          'name' => 'Barritas de proteínas',            'type' => ItemType::Energy, 'power' => 4],
            ['slug' => 'bebida_energetica', 'name' => 'Bebida energética',                'type' => ItemType::Energy, 'power' => 5],
            ['slug' => 'estimulante',       'name' => 'Estimulante militar',              'type' => ItemType::Energy, 'power' => 10],
        ];

        foreach ($items as $data) {
            Item::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
