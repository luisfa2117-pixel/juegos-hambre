<?php

namespace App\Simulation;

/**
 * Lo que ha pasado al aplicar un evento. En Java esto eran los System.out.println;
 * aquí lo devolvemos como datos y la vista decidirá cómo mostrarlo.
 */
class EventResult
{
    /**
     * @param  string  $text  El texto del evento con los nombres ya puestos
     * @param  string[]  $notes  Detalles extra: defensas usadas, objetos, curaciones...
     * @param  string[]  $deaths  Nombres de los tributos que han muerto en este evento
     * @param  string[]  $participants  Nombres de todos los que han participado, en orden {A}, {B}...
     * @param  array<int, array{victim: string, killers: string[]}>  $kills  Quién ha matado a quién
     *                                                                       (killers vacío = accidente)
     * @param  string|null  $arena  Si es el anuncio de una catástrofe, su nombre
     * @param  bool  $gift  Si es el aviso de un regalo de patrocinador
     */
    public function __construct(
        public readonly string $text,
        public readonly array $notes = [],
        public readonly array $deaths = [],
        public readonly array $participants = [],
        public readonly array $kills = [],
        public readonly ?string $arena = null,
        public readonly bool $gift = false,
    ) {}

    /** En un array, para guardarlo en la base de datos. */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'notes' => $this->notes,
            'deaths' => $this->deaths,
            'participants' => $this->participants,
            'kills' => $this->kills,
            'arena' => $this->arena,
            'gift' => $this->gift,
        ];
    }
}
