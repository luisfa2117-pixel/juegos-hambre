<?php

namespace App\Console\Commands;

use App\Enums\Terrain;
use App\Models\ArenaEvent;
use App\Models\GameEvent;
use Database\Seeders\GameEventSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Vuelca los eventos de la base de datos a database/data/eventos.json,
 * con el mismo formato que lee el GameEventSeeder.
 *
 * Así, lo que crees o edites en la web no se pierde al hacer migrate:fresh --seed,
 * y queda guardado en Git junto al resto del proyecto.
 */
class ExportEventsCommand extends Command
{
    protected $signature = 'events:export {--path= : Fichero de destino (por defecto, database/data/eventos.json)}';

    protected $description = 'Guarda los eventos de la base de datos en el JSON de los seeders';

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('data/eventos.json');
        $events = GameEvent::with(['requiresItem', 'givesItem'])->orderBy('id')->get();
        $data = [];

        foreach (GameEventSeeder::CATEGORY_MAP as $key => $category) {
            $data[$key] = $events
                ->where('category', $category)
                ->map(fn (GameEvent $event) => $this->toJson($event))
                ->values()
                ->all();
        }

        $data['catastrofes'] = ArenaEvent::with(['events.requiresItem', 'events.givesItem'])->orderBy('id')->get()
            ->map(fn (ArenaEvent $arena) => array_filter([
                'id' => $arena->slug,
                'nombre' => $arena->name,
                'anuncio' => $arena->intro,
                'escenarios' => $this->terrains($arena->terrains),
                'eventos' => $arena->events->sortBy('id')->map(fn (GameEvent $event) => $this->toJson($event))->values()->all(),
            ], fn ($value) => $value !== null))
            ->all();

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->info("{$events->count()} eventos guardados en {$path}");

        return self::SUCCESS;
    }

    /** El formato del JSON de tu proyecto en Java (danioPropio, energiaRival...) */
    private function toJson(GameEvent $event): array
    {
        $json = [
            'descripcion' => $event->text,
            'numParticipantes' => $event->participants,
            'danioPropio' => $event->actor_hp,
            'danioRival' => $event->target_hp,
            'energiaPropia' => $event->actor_energy,
            'energiaRival' => $event->target_energy,
        ];

        if ($event->phase) {
            $json['fase'] = array_search($event->phase, GameEventSeeder::PHASE_MAP, true);
        }

        if ($event->personality) {
            $json['personalidad'] = array_search($event->personality, GameEventSeeder::PERSONALITY_MAP, true);
        }

        if ($event->givesItem) {
            $json['objeto'] = strtoupper($event->givesItem->slug);
        }

        if ($event->requiresItem) {
            $json['requiere'] = strtoupper($event->requiresItem->slug);
        }

        if ($event->terrains !== null) {
            $json['escenarios'] = $this->terrains($event->terrains);
        }

        return $json;
    }

    /**
     * ['city', 'desert'] => ['ciudad', 'desierto'], como en el JSON
     *
     * @param  string[]|null  $terrains
     * @return string[]|null
     */
    private function terrains(?array $terrains): ?array
    {
        if ($terrains === null) {
            return null;
        }

        return array_map(
            fn (string $value) => array_search(Terrain::from($value), GameEventSeeder::TERRAIN_MAP, true),
            $terrains,
        );
    }
}
