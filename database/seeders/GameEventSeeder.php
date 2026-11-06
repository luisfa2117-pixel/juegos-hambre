<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\GameEvent;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GameEventSeeder extends Seeder
{
    /** Clave del JSON de Java => categoría en la base de datos (también la usa events:export) */
    public const CATEGORY_MAP = [
        'eventosIndividuales'   => EventCategory::Solo,
        'eventosDuo'            => EventCategory::Duo,
        'eventosTriple'         => EventCategory::Trio,
        'eventosCuatro'         => EventCategory::Quartet,
        'eventosAmigo'          => EventCategory::Ally,
        'hacerAmigo'            => EventCategory::Befriend,
        'eventosDescubrimiento' => EventCategory::Discovery,
        'eventosArma'           => EventCategory::Weapon,
        'traicionAmigo'         => EventCategory::Betrayal,
    ];

    public function run(): void
    {
        $json = File::get(database_path('data/eventos.json'));
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        // ['taser' => 1, 'cuchillo' => 2, ...]
        $itemIds = Item::pluck('id', 'slug');

        foreach (self::CATEGORY_MAP as $key => $category) {
            foreach ($data[$key] ?? [] as $raw) {
                GameEvent::updateOrCreate(
                    ['category' => $category, 'text' => $raw['descripcion']],
                    $this->mapFields($raw, $itemIds),
                );
            }
        }
    }

    /** Traduce un evento con los nombres de campos de Java a las columnas nuevas. */
    private function mapFields(array $raw, Collection $itemIds): array
    {
        // En Java, modEnergia se aplicaba a todos los participantes
        $energy = $raw['modEnergia'] ?? 0;

        return [
            'participants'     => $raw['numParticipantes'] ?? 2,
            'actor_hp'         => $raw['danioPropio'] ?? 0,
            'target_hp'        => $raw['danioRival'] ?? 0,
            'actor_energy'     => $raw['energiaPropia'] ?? $energy,
            'target_energy'    => $raw['energiaRival'] ?? $energy,
            'gives_item_id'    => $this->itemId($raw['objeto'] ?? null, $itemIds),
            'requires_item_id' => $this->itemId($raw['requiere'] ?? $raw['arma'] ?? null, $itemIds),
        ];
    }

    /** 'BOTIQUIN' (nombre del enum de Java) => id del objeto 'botiquin' */
    private function itemId(?string $javaName, Collection $itemIds): ?int
    {
        if ($javaName === null) {
            return null;
        }

        $slug = strtolower($javaName);

        if (! $itemIds->has($slug)) {
            throw new RuntimeException("Objeto desconocido en eventos.json: {$javaName}");
        }

        return $itemIds[$slug];
    }
}
