<?php

namespace App\Simulation;

use App\Enums\EventCategory;
use App\Enums\ItemType;
use App\Models\GameEvent;
use LogicException;
use Random\Randomizer;
use RuntimeException;

/**
 * Una partida en curso: los tributos y las rondas.
 * Junta lo que hacían tu Juego.java (la ronda) y GestorEventos.elegirEvento (qué pasa).
 */
class Simulation
{
    // Probabilidades en %. Son las de tu GestorEventos, con nombre para poder ajustarlas.
    public const GROUP_EVENT_CHANCE = 10;   // evento de 3 o 4 tributos
    public const PAIR_EVENT_CHANCE = 40;    // evento de 2 tributos (el resto, 50 %, es individual)
    public const FRIENDSHIP_CHANCE = 25;    // dos desconocidos se hacen aliados en vez de pelear
    public const BETRAYAL_CHANCE = 15;      // un aliado traiciona al otro
    public const WEAPON_CHANCE = 60;        // quien lleva arma la usa en una pelea
    public const DISCOVERY_CHANCE = 30;     // un evento individual es un descubrimiento

    private int $round = 0;

    /** @var Tribute[] */
    private array $tributes;

    /** @param Tribute[] $tributes */
    public function __construct(
        array $tributes,
        private readonly EventPool $events,
        private readonly Randomizer $random,
        private readonly EventResolver $resolver = new EventResolver(),
    ) {
        $this->tributes = array_values($tributes);
    }

    public function round(): int
    {
        return $this->round;
    }

    /** @return Tribute[] */
    public function tributes(): array
    {
        return $this->tributes;
    }

    /** @return Tribute[] */
    public function alive(): array
    {
        return array_values(array_filter($this->tributes, fn (Tribute $t) => $t->isAlive()));
    }

    public function isOver(): bool
    {
        return count($this->alive()) <= 1;
    }

    /** El último superviviente, o null si la partida sigue (o han muerto todos). */
    public function winner(): ?Tribute
    {
        $alive = $this->alive();

        return count($alive) === 1 ? $alive[0] : null;
    }

    /**
     * Juega una ronda: cada tributo vivo participa en exactamente un evento.
     *
     * @return EventResult[] Lo que ha pasado, en orden
     */
    public function runRound(): array
    {
        if ($this->isOver()) {
            throw new LogicException('La partida ya ha terminado');
        }

        $this->round++;
        $results = [];

        $finalDuel = $this->breakFinalAlliance();
        if ($finalDuel !== null) {
            $results[] = $finalDuel;
        }

        $pending = $this->random->shuffleArray($this->alive());

        while ($pending !== []) {
            [$event, $participants] = $this->chooseEvent($pending);

            $results[] = $this->resolver->apply($event, $participants);

            // Quienes acaban de participar ya no esperan turno en esta ronda
            $pending = array_values(array_filter(
                $pending,
                fn (Tribute $t) => ! in_array($t, $participants, true),
            ));
        }

        return $results;
    }

    /**
     * Si los dos últimos supervivientes son aliados, la alianza se rompe:
     * solo puede haber un ganador. Sin esto, dos aliados podían pasarse
     * decenas de rondas cuidándose el uno al otro.
     */
    private function breakFinalAlliance(): ?EventResult
    {
        $alive = $this->alive();

        if (count($alive) !== 2 || ! $alive[0]->isAllyOf($alive[1])) {
            return null;
        }

        [$a, $b] = $alive;
        $a->breakAllianceWith($b);

        return new EventResult(
            text: "Solo quedan {$a->name} y {$b->name}. Solo puede haber un ganador.",
            notes: ["💔 La alianza entre {$a->name} y {$b->name} se ha roto."],
        );
    }

    /**
     * Tira el "dado" y decide el próximo evento con los primeros tributos en espera.
     *
     * @param Tribute[] $pending
     * @return array{0: GameEvent, 1: Tribute[]}
     */
    private function chooseEvent(array $pending): array
    {
        $available = count($pending);
        $roll = $this->random->getInt(1, 100);
        $choice = null;

        if ($roll <= self::GROUP_EVENT_CHANCE && $available >= 3) {
            $size = ($available >= 4 && $this->chance(50)) ? 4 : 3;
            $choice = $this->groupEvent(array_slice($pending, 0, $size));
        } elseif ($roll <= self::GROUP_EVENT_CHANCE + self::PAIR_EVENT_CHANCE && $available >= 2) {
            $choice = $this->pairEvent($pending[0], $pending[1]);
        }

        // Si no ha tocado evento de grupo o de pareja (o no había ninguno posible), individual
        return $choice ?? $this->soloEvent($pending[0]);
    }

    /** @param Tribute[] $group */
    private function groupEvent(array $group): ?array
    {
        $category = count($group) === 4 ? EventCategory::Quartet : EventCategory::Trio;
        $event = $this->events->pick($category, $group[0], $this->random);

        return $event !== null ? [$event, $group] : null;
    }

    private function pairEvent(Tribute $a, Tribute $b): ?array
    {
        if ($a->isAllyOf($b)) {
            $category = $this->chance(self::BETRAYAL_CHANCE) ? EventCategory::Betrayal : EventCategory::Ally;
        } elseif ($this->chance(self::FRIENDSHIP_CHANCE)) {
            $category = EventCategory::Befriend;
        } elseif ($a->bestItemOfType(ItemType::Weapon) !== null && $this->chance(self::WEAPON_CHANCE)) {
            $category = EventCategory::Weapon;
        } else {
            $category = EventCategory::Duo;
        }

        $event = $this->events->pick($category, $a, $this->random);

        // Si lleva un arma que no tiene eventos propios, pelea normal
        if ($event === null && $category === EventCategory::Weapon) {
            $event = $this->events->pick(EventCategory::Duo, $a, $this->random);
        }

        return $event !== null ? [$event, [$a, $b]] : null;
    }

    private function soloEvent(Tribute $a): array
    {
        $category = $this->chance(self::DISCOVERY_CHANCE) ? EventCategory::Discovery : EventCategory::Solo;

        $event = $this->events->pick($category, $a, $this->random)
            ?? $this->events->pick(EventCategory::Solo, $a, $this->random)
            ?? $this->events->pick(EventCategory::Discovery, $a, $this->random);

        if ($event === null) {
            throw new RuntimeException('No hay eventos individuales: revisa los seeders');
        }

        return [$event, [$a]];
    }

    /** true con un $percent % de probabilidad */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }
}
