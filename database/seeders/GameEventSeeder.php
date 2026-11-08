<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\Personality;
use App\Enums\Terrain;
use App\Models\ArenaEvent;
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
        'eventosIndividuales' => EventCategory::Solo,
        'eventosDuo' => EventCategory::Duo,
        'eventosTriple' => EventCategory::Trio,
        'eventosCuatro' => EventCategory::Quartet,
        'eventosAmigo' => EventCategory::Ally,
        'hacerAmigo' => EventCategory::Befriend,
        'eventosDescubrimiento' => EventCategory::Discovery,
        'eventosArma' => EventCategory::Weapon,
        'traicionAmigo' => EventCategory::Betrayal,
        'aliadosAtacan' => EventCategory::AllyAttack,
    ];

    /** Valor de "fase" en el JSON => fase en la base de datos (sin "fase" = de día o de noche) */
    public const PHASE_MAP = [
        'cornucopia' => EventPhase::Bloodbath,
        'dia' => EventPhase::Day,
        'noche' => EventPhase::Night,
    ];

    /** Valor de "personalidad" en el JSON => personalidad en la base de datos (sin ella = para cualquiera) */
    public const PERSONALITY_MAP = [
        'agresivo' => Personality::Aggressive,
        'cobarde' => Personality::Coward,
        'leal' => Personality::Loyal,
        'traicionero' => Personality::Treacherous,
        'ingenioso' => Personality::Resourceful,
        'sociable' => Personality::Sociable,
    ];

    /** Valor de "escenarios" en el JSON => arena (sin "escenarios" = en todas) */
    public const TERRAIN_MAP = [
        'ciudad' => Terrain::City,
        'desierto' => Terrain::Desert,
        'cumbres' => Terrain::Peaks,
        'pantano' => Terrain::Swamp,
    ];

    public function run(): void
    {
        // ['taser' => 1, 'cuchillo' => 2, ...]
        $itemIds = Item::pluck('id', 'slug');

        $this->seedFile(database_path('data/eventos.json'), $itemIds);

        // Las arenas temáticas: sus propios eventos y catástrofes, y en qué arenas encajan los de eventos.json
        $arenas = database_path('data/arenas.json');
        if (File::exists($arenas)) {
            $data = $this->seedFile($arenas, $itemIds);
            $this->tagExisting($data);
        }
    }

    /** Crea o actualiza los eventos y catástrofes de un fichero con el formato de eventos.json */
    private function seedFile(string $path, Collection $itemIds): array
    {
        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        // eventos.json tiene las categorías arriba del todo; arenas.json, dentro de "eventos"
        $byCategory = $data['eventos'] ?? $data;

        foreach (self::CATEGORY_MAP as $key => $category) {
            foreach ($byCategory[$key] ?? [] as $raw) {
                GameEvent::updateOrCreate(
                    ['category' => $category, 'text' => $raw['descripcion']],
                    $this->mapFields($raw, $itemIds),
                );
            }
        }

        // Las catástrofes: cada una con su anuncio y sus propios eventos (de una persona)
        foreach ($data['catastrofes'] ?? [] as $raw) {
            $arena = ArenaEvent::updateOrCreate(
                ['slug' => $raw['id']],
                ['name' => $raw['nombre'], 'intro' => $raw['anuncio'], 'terrains' => $this->terrains($raw['escenarios'] ?? null)],
            );

            foreach ($raw['eventos'] as $eventRaw) {
                GameEvent::updateOrCreate(
                    ['category' => EventCategory::Arena, 'text' => $eventRaw['descripcion']],
                    [
                        ...$this->mapFields(['numParticipantes' => 1, ...$eventRaw], $itemIds),
                        'arena_event_id' => $arena->id,
                    ],
                );
            }
        }

        return $data;
    }

    /**
     * Marca en qué arenas encajan eventos que ya existen (los de eventos.json hablan de metros y
     * rascacielos: en el desierto no pegan). Si un texto ya no existe, se ignora.
     */
    private function tagExisting(array $data): void
    {
        foreach ($data['etiquetas'] ?? [] as $tag) {
            GameEvent::where('text', $tag['descripcion'])
                ->where('category', '!=', EventCategory::Arena)
                ->update(['terrains' => json_encode($this->terrains($tag['escenarios']))]);
        }

        foreach ($data['catastrofesDeLaCiudad'] ?? [] as $slug) {
            ArenaEvent::where('slug', $slug)->update(['terrains' => json_encode([Terrain::City->value])]);
        }
    }

    /** Traduce un evento con los nombres de campos de Java a las columnas nuevas. */
    private function mapFields(array $raw, Collection $itemIds): array
    {
        // En Java, modEnergia se aplicaba a todos los participantes
        $energy = $raw['modEnergia'] ?? 0;

        return [
            'participants' => $raw['numParticipantes'] ?? 2,
            'phase' => $this->phase($raw['fase'] ?? null),
            'personality' => $this->personality($raw['personalidad'] ?? null),
            'actor_hp' => $raw['danioPropio'] ?? 0,
            'target_hp' => $raw['danioRival'] ?? 0,
            'actor_energy' => $raw['energiaPropia'] ?? $energy,
            'target_energy' => $raw['energiaRival'] ?? $energy,
            'gives_item_id' => $this->itemId($raw['objeto'] ?? null, $itemIds),
            'requires_item_id' => $this->itemId($raw['requiere'] ?? $raw['arma'] ?? null, $itemIds),
            'terrains' => $this->terrains($raw['escenarios'] ?? null),
        ];
    }

    /**
     * ['ciudad', 'desierto'] => ['city', 'desert'] (null = en todas)
     *
     * @param  string[]|null  $values
     * @return string[]|null
     */
    private function terrains(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return array_map(
            fn (string $value) => (self::TERRAIN_MAP[$value]
                ?? throw new RuntimeException("Arena desconocida: {$value} (usa ciudad, desierto, cumbres o pantano)"))->value,
            $values,
        );
    }

    private function phase(?string $value): ?EventPhase
    {
        if ($value === null) {
            return null;
        }

        return self::PHASE_MAP[$value]
            ?? throw new RuntimeException("Fase desconocida en eventos.json: {$value} (usa cornucopia, dia o noche)");
    }

    private function personality(?string $value): ?Personality
    {
        if ($value === null) {
            return null;
        }

        return self::PERSONALITY_MAP[$value]
            ?? throw new RuntimeException("Personalidad desconocida en eventos.json: {$value}");
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
