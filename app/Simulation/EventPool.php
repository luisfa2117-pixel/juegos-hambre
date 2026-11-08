<?php

namespace App\Simulation;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\Terrain;
use App\Models\ArenaEvent;
use App\Models\GameEvent;
use Random\Randomizer;

/**
 * Todos los eventos posibles en una arena, agrupados por categoría.
 * Sustituye a las listas eventosIndividuales, eventosDuo... de tu GestorEventos.
 */
class EventPool
{
    /** En una arena temática, probabilidad de que salga un evento propio de la arena si lo hay */
    public const OWN_TERRAIN_CHANCE = 40;

    /** @var array<string, GameEvent[]> ['solo' => [...], 'duo' => [...], ...] */
    private array $byCategory = [];

    /** @var array<string, ArenaEvent> Las catástrofes, por su slug */
    private array $arenas = [];

    /** @var array<string, GameEvent[]> Los eventos de cada catástrofe, por el slug de la catástrofe */
    private array $arenaEvents = [];

    /**
     * Se queda solo con los eventos (y catástrofes) que pueden pasar en $terrain:
     * los que no tienen arena (valen para todas) y los que la incluyen.
     *
     * @param  iterable<GameEvent>  $events
     */
    public function __construct(iterable $events, private readonly Terrain $terrain = Terrain::City)
    {
        foreach ($events as $event) {
            if ($event->category === EventCategory::Arena) {
                $arena = $event->arenaEvent;

                if ($arena !== null && $this->fits($arena->terrains)) {
                    $this->arenas[$arena->slug] = $arena;
                    $this->arenaEvents[$arena->slug][] = $event;
                }

                continue;
            }

            if ($this->fits($event->terrains)) {
                $this->byCategory[$event->category->value][] = $event;
            }
        }
    }

    /** @param string[]|null $terrains Las arenas donde puede pasar (null = en todas) */
    private function fits(?array $terrains): bool
    {
        return $terrains === null || in_array($this->terrain->value, $terrains, true);
    }

    public function terrain(): Terrain
    {
        return $this->terrain;
    }

    /** Carga todos los eventos con sus objetos y catástrofes en pocas consultas (en vez de una por evento). */
    public static function fromDatabase(Terrain $terrain = Terrain::City): self
    {
        // orderBy('id'): sin orden, Postgres puede devolver las filas en otro orden cada vez,
        // y con la misma semilla saldría una partida distinta
        return new self(GameEvent::with(['requiresItem', 'givesItem', 'arenaEvent'])->orderBy('id')->get(), $terrain);
    }

    public function hasArenas(): bool
    {
        return $this->arenas !== [];
    }

    /** Una catástrofe al azar, o null si no hay ninguna */
    public function pickArena(Randomizer $random): ?ArenaEvent
    {
        $arenas = array_values($this->arenas);

        return $arenas === [] ? null : $arenas[$random->getInt(0, count($arenas) - 1)];
    }

    /**
     * Lo que le pasa a $actor durante la catástrofe: uno de sus eventos que pueda protagonizar,
     * evitando los ya usados en la ronda mientras haya otros.
     *
     * @param  GameEvent[]  $avoid
     */
    public function pickArenaEvent(ArenaEvent $arena, Tribute $actor, Randomizer $random, array $avoid = []): ?GameEvent
    {
        return $this->chooseFrom($this->usableBy($this->arenaEvents[$arena->slug] ?? [], $actor), $random, $avoid);
    }

    /**
     * Elige al azar un evento de la categoría que el actor PUEDA protagonizar
     * (si requiere un objeto, tiene que llevarlo) y que encaje con la fase:
     * - de día o de noche: los eventos sin fase y los de esa fase;
     * - en el baño de sangre: los de la Cornucopia, o si no hay, los sin fase.
     * Sin fase ($phase = null) vale cualquiera.
     *
     * Los eventos de $avoid (los que ya han salido esta ronda) se evitan mientras haya otros;
     * si no queda ninguno más, se permite repetir. Devuelve null si no hay ninguno posible.
     *
     * Con $ownPersonality, se queda solo con los eventos propios de la personalidad del actor
     * (si no hay ninguno, vale cualquiera).
     *
     * @param  GameEvent[]  $avoid
     */
    public function pick(
        EventCategory $category,
        Tribute $actor,
        Randomizer $random,
        ?EventPhase $phase = null,
        array $avoid = [],
        bool $ownPersonality = false,
    ): ?GameEvent {
        $candidates = $this->usableBy($this->byCategory[$category->value] ?? [], $actor);

        if ($phase !== null) {
            $candidates = $this->forPhase($candidates, $phase);
        }

        if ($ownPersonality) {
            $own = array_values(array_filter($candidates, fn (GameEvent $event) => $event->personality !== null));

            if ($own !== []) {
                $candidates = $own;
            }
        }

        // En el desierto, en las cumbres o en el pantano, a menudo sale uno de sus propios eventos:
        // si no, entre tantos eventos generales apenas se notaría en qué arena estás
        if ($this->terrain->favoursOwnEvents()) {
            $ownTerrain = array_values(array_filter($candidates, fn (GameEvent $event) => $event->terrains !== null));

            if ($ownTerrain !== [] && $random->getInt(1, 100) <= self::OWN_TERRAIN_CHANCE) {
                $candidates = $ownTerrain;
            }
        }

        return $this->chooseFrom($candidates, $random, $avoid);
    }

    /** ¿Hay algún evento de esta categoría propio de la personalidad de este tributo? */
    public function hasPersonalityEvents(EventCategory $category, Tribute $actor): bool
    {
        if ($actor->personality === null) {
            return false;
        }

        foreach ($this->byCategory[$category->value] ?? [] as $event) {
            if ($event->personality === $actor->personality) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los eventos que $actor puede protagonizar: si requieren un objeto, tiene que llevarlo,
     * y si son de una personalidad, tiene que ser la suya.
     *
     * @param  GameEvent[]  $events
     * @return GameEvent[]
     */
    private function usableBy(array $events, Tribute $actor): array
    {
        return array_values(array_filter(
            $events,
            fn (GameEvent $event) => ($event->requiresItem === null || $actor->hasItem($event->requiresItem))
                && ($event->personality === null || $event->personality === $actor->personality),
        ));
    }

    /**
     * Elige uno al azar, evitando los de $avoid mientras haya otros.
     *
     * @param  GameEvent[]  $candidates
     * @param  GameEvent[]  $avoid
     */
    private function chooseFrom(array $candidates, Randomizer $random, array $avoid): ?GameEvent
    {
        $fresh = array_values(array_filter($candidates, fn (GameEvent $event) => ! in_array($event, $avoid, true)));

        if ($fresh !== []) {
            $candidates = $fresh;
        }

        if ($candidates === []) {
            return null;
        }

        return $candidates[$random->getInt(0, count($candidates) - 1)];
    }

    /**
     * @param  GameEvent[]  $events
     * @return GameEvent[]
     */
    private function forPhase(array $events, EventPhase $phase): array
    {
        $only = fn (?EventPhase $wanted) => array_values(array_filter(
            $events,
            fn (GameEvent $event) => $event->phase === $wanted,
        ));

        if ($phase === EventPhase::Bloodbath) {
            $bloodbath = $only(EventPhase::Bloodbath);

            return $bloodbath !== [] ? $bloodbath : $only(null);
        }

        return [...$only(null), ...$only($phase)];
    }
}
