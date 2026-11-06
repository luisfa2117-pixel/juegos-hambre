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
            ['slug' => 'taser',             'name' => 'Taser aturdidor',    'type' => ItemType::Weapon, 'power' => 3],
            ['slug' => 'cuchillo',          'name' => 'Cuchillo de combate', 'type' => ItemType::Weapon, 'power' => 5],
            ['slug' => 'pistolaclavos',     'name' => 'Pistola de clavos',  'type' => ItemType::Weapon, 'power' => 8],
            ['slug' => 'chaleco',           'name' => 'Chaleco antibalas',  'type' => ItemType::Armor,  'power' => 5],
            ['slug' => 'botiquin',          'name' => 'Botiquín médico',    'type' => ItemType::Heal,   'power' => 10],
            ['slug' => 'bebida_energetica', 'name' => 'Bebida energética',  'type' => ItemType::Energy, 'power' => 5],
        ];

        foreach ($items as $data) {
            Item::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
