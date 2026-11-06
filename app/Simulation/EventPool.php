<?php

namespace App\Simulation;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Models\GameEvent;
use Random\Randomizer;

/**
 * Todos los eventos posibles, agrupados por categoría.
 * Sustituye a las listas eventosIndividuales, eventosDuo... de tu GestorEventos.
 */
class EventPool
{
    /** @var array<string, GameEvent[]> ['solo' => [...], 'duo' => [...], ...] */
    private array $byCategory = [];

    /** @param iterable<GameEvent> $events */
    public function __construct(iterable $events)
    {
        foreach ($events as $event) {
            $this->byCategory[$event->category->value][] = $event;
        }
    }

    /** Carga todos los eventos con sus objetos en dos consultas (en vez de una por evento). */
    public static function fromDatabase(): self
    {
        return new self(GameEvent::with(['requiresItem', 'givesItem'])->get());
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
     * @param GameEvent[] $avoid
     */
    public function pick(
        EventCategory $category,
        Tribute $actor,
        Randomizer $random,
        ?EventPhase $phase = null,
        array $avoid = [],
    ): ?GameEvent {
        $candidates = array_values(array_filter(
            $this->byCategory[$category->value] ?? [],
            fn (GameEvent $event) => $event->requiresItem === null || $actor->hasItem($event->requiresItem),
        ));

        if ($phase !== null) {
            $candidates = $this->forPhase($candidates, $phase);
        }

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
     * @param GameEvent[] $events
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
