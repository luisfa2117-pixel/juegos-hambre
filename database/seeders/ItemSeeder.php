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
            // Armas: el daño lo pone cada evento; 'uses' es cuántos ataques aguantan antes de romperse
            ['slug' => 'bate_clavos',       'name' => 'Bate con clavos',                  'type' => ItemType::Weapon, 'power' => 4, 'uses' => 4],
            ['slug' => 'taser',             'name' => 'Taser aturdidor',                  'type' => ItemType::Weapon, 'power' => 3, 'uses' => 3],
            ['slug' => 'cuchillo',          'name' => 'Cuchillo de combate',              'type' => ItemType::Weapon, 'power' => 5, 'uses' => 4],
            ['slug' => 'machete',           'name' => 'Machete oxidado',                  'type' => ItemType::Weapon, 'power' => 6, 'uses' => 4],
            ['slug' => 'ballesta',          'name' => 'Ballesta de caza',                 'type' => ItemType::Weapon, 'power' => 7, 'uses' => 3],
            ['slug' => 'pistolaclavos',     'name' => 'Pistola de clavos',                'type' => ItemType::Weapon, 'power' => 8, 'uses' => 3],
            ['slug' => 'granada_pem',       'name' => 'Granada de pulso',                 'type' => ItemType::Weapon, 'power' => 9, 'uses' => 1],
            ['slug' => 'dron_armado',       'name' => 'Dron armado',                      'type' => ItemType::Weapon, 'power' => 9, 'uses' => 3],
            ['slug' => 'pistola',           'name' => 'Pistola sin número de serie',      'type' => ItemType::Weapon, 'power' => 10, 'uses' => 3],
            ['slug' => 'lanzallamas',       'name' => 'Lanzallamas casero',               'type' => ItemType::Weapon, 'power' => 11, 'uses' => 2],
            ['slug' => 'rifle_riel',        'name' => 'Rifle de riel electromagnético',   'type' => ItemType::Weapon, 'power' => 14, 'uses' => 2],

            // Defensas: cada golpe que paran absorbe tanto daño como su poder y gasta un uso
            ['slug' => 'casco',             'name' => 'Casco antidisturbios',             'type' => ItemType::Armor,  'power' => 3, 'uses' => 1],
            ['slug' => 'chaleco',           'name' => 'Chaleco antibalas',                'type' => ItemType::Armor,  'power' => 5, 'uses' => 2],
            ['slug' => 'escudo_policial',   'name' => 'Escudo antidisturbios',            'type' => ItemType::Armor,  'power' => 6, 'uses' => 2],
            ['slug' => 'escudo',            'name' => 'Generador de escudo personal',     'type' => ItemType::Armor,  'power' => 8, 'uses' => 2],
            ['slug' => 'exotraje',          'name' => 'Exoesqueleto blindado',            'type' => ItemType::Armor,  'power' => 10, 'uses' => 3],

            // Curación: vida que recuperan
            ['slug' => 'vendas',            'name' => 'Vendas y desinfectante',           'type' => ItemType::Heal,   'power' => 5, 'uses' => 1],
            ['slug' => 'botiquin',          'name' => 'Botiquín médico',                  'type' => ItemType::Heal,   'power' => 10, 'uses' => 1],
            ['slug' => 'nanobots',          'name' => 'Inyector de nanobots',             'type' => ItemType::Heal,   'power' => 15, 'uses' => 1],

            // Energía: energía que recuperan
            ['slug' => 'barritas',          'name' => 'Barritas de proteínas',            'type' => ItemType::Energy, 'power' => 4, 'uses' => 1],
            ['slug' => 'bebida_energetica', 'name' => 'Bebida energética',                'type' => ItemType::Energy, 'power' => 5, 'uses' => 1],
            ['slug' => 'estimulante',       'name' => 'Estimulante militar',              'type' => ItemType::Energy, 'power' => 10, 'uses' => 1],
        ];

        foreach ($items as $data) {
            Item::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
