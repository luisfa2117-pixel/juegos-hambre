<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\Personality;
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

        // Las catástrofes: cada una con su anuncio y sus propios eventos (de una persona)
        foreach ($data['catastrofes'] ?? [] as $raw) {
            $arena = ArenaEvent::updateOrCreate(
                ['slug' => $raw['id']],
                ['name' => $raw['nombre'], 'intro' => $raw['anuncio']],
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
        ];
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
