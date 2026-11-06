<?php

namespace App\Simulation;

/**
 * Lo que ha pasado al aplicar un evento. En Java esto eran los System.out.println;
 * aquí lo devolvemos como datos y la vista decidirá cómo mostrarlo.
 */
class EventResult
{
    /**
     * @param string   $text   El texto del evento con los nombres ya puestos
     * @param string[] $notes  Detalles extra: defensas usadas, objetos, curaciones...
     * @param string[] $deaths Nombres de los tributos que han muerto en este evento
     */
    public function __construct(
        public readonly string $text,
        public readonly array $notes = [],
        public readonly array $deaths = [],
    ) {
    }
}
