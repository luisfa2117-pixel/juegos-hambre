<?php

namespace App\Simulation;

use App\Enums\EventCategory;
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
     * Elige al azar un evento de la categoría que el actor PUEDA protagonizar:
     * si el evento requiere un objeto, el actor tiene que llevarlo en la mochila.
     * Devuelve null si no hay ninguno posible.
     */
    public function pick(EventCategory $category, Tribute $actor, Randomizer $random): ?GameEvent
    {
        $candidates = array_values(array_filter(
            $this->byCategory[$category->value] ?? [],
            fn (GameEvent $event) => $event->requiresItem === null || $actor->hasItem($event->requiresItem),
        ));

        if ($candidates === []) {
            return null;
        }

        return $candidates[$random->getInt(0, count($candidates) - 1)];
    }
}
