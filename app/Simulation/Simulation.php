<?php

namespace App\Simulation;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\Personality;
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
    public const GROUP_EVENT_CHANCE = 15;   // evento de 3 o 4 tributos
    public const PAIR_EVENT_CHANCE = 50;    // evento de 2 tributos (el resto, 35 %, es individual)
    public const FRIENDSHIP_CHANCE = 35;    // dos desconocidos se hacen aliados en vez de pelear
    public const BETRAYAL_CHANCE = 35;      // un aliado traiciona al otro
    public const WEAPON_CHANCE = 90;        // quien lleva arma la usa en una pelea
    public const DISCOVERY_CHANCE = 40;     // un evento individual es un descubrimiento
    public const ALLY_ATTACK_CHANCE = 40;   // dos aliados atacan juntos a un tercero (si se puede)

    // Catástrofes de los Vigilantes: a partir de la ronda 4 (Día 2), y como mucho una cada 4 rondas
    public const ARENA_EVENT_CHANCE = 12;
    public const ARENA_FIRST_ROUND = 4;
    public const ARENA_COOLDOWN = 4;

    // En el baño de sangre todos corren hacia la Cornucopia: más grupos y más objetos
    public const BLOODBATH_GROUP_CHANCE = 30;
    public const BLOODBATH_DISCOVERY_CHANCE = 60;

    /** Probabilidad de que, si hay eventos propios de la personalidad del actor, salga uno de ellos */
    public const PERSONALITY_EVENT_CHANCE = 35;

    /** Los compañeros de distrito se alían más fácilmente */
    public const DISTRICT_FRIENDSHIP_BONUS = 25;

    /** Máximo de miembros por alianza, para que no acaben todos en el mismo bando */
    public const MAX_ALLIANCE_SIZE = 3;

    private int $round = 0;

    private EventPhase $phase = EventPhase::Bloodbath;

    /** @var GameEvent[] Los eventos que ya han salido en esta ronda, para no repetirlos */
    private array $usedThisRound = [];

    /** Ronda de la última catástrofe (muy negativa al principio: todavía no ha habido ninguna) */
    private int $lastArenaRound = -1000;

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

    /** La fase de la última ronda jugada (o de la primera, si aún no ha empezado) */
    public function phase(): EventPhase
    {
        return $this->phase;
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
        $this->phase = EventPhase::forRound($this->round);
        $this->usedThisRound = [];
        $results = [];

        $dissolved = $this->dissolveFinalAlliance();
        if ($dissolved !== null) {
            $results[] = $dissolved;
        }

        if ($this->shouldStartArena()) {
            return [...$results, ...$this->arenaRound()];
        }

        $pending = $this->random->shuffleArray($this->alive());

        while ($pending !== []) {
            [$event, $participants] = $this->chooseEvent($pending);
            $this->usedThisRound[] = $event;

            $results[] = $this->resolver->apply($event, $participants);

            // Quienes acaban de participar ya no esperan turno en esta ronda
            $pending = array_values(array_filter(
                $pending,
                fn (Tribute $t) => ! in_array($t, $participants, true),
            ));
        }

        return $results;
    }

    /** ¿Toca catástrofe en esta ronda? Primero se mira si es posible y solo entonces se tira el dado */
    private function shouldStartArena(): bool
    {
        return $this->phase !== EventPhase::Bloodbath
            && $this->round >= self::ARENA_FIRST_ROUND
            && $this->round - $this->lastArenaRound >= self::ARENA_COOLDOWN
            && $this->events->hasArenas()
            && $this->chance(self::ARENA_EVENT_CHANCE);
    }

    /**
     * Una ronda de catástrofe: el anuncio y, después, lo que le pasa a cada tributo vivo.
     *
     * @return EventResult[]
     */
    private function arenaRound(): array
    {
        $arena = $this->events->pickArena($this->random);
        $this->lastArenaRound = $this->round;

        $results = [new EventResult(text: $arena->intro, arena: $arena->name)];

        foreach ($this->random->shuffleArray($this->alive()) as $tribute) {
            // Si la catástrofe no tiene ningún evento posible para él, le pasa algo normal
            $event = $this->events->pickArenaEvent($arena, $tribute, $this->random, $this->usedThisRound)
                ?? $this->pick(EventCategory::Solo, $tribute, anyPhase: true)
                ?? throw new RuntimeException('No hay eventos individuales: revisa los seeders');

            $this->usedThisRound[] = $event;
            $results[] = $this->resolver->apply($event, [$tribute]);
        }

        return $results;
    }

    /**
     * Si todos los supervivientes son de la misma alianza, se disuelve:
     * solo puede haber un ganador. Sin esto, los últimos aliados podían pasarse
     * decenas de rondas cuidándose unos a otros.
     */
    private function dissolveFinalAlliance(): ?EventResult
    {
        $alive = $this->alive();

        if (count($alive) < 2 || count($alive[0]->alliance()) !== count($alive)) {
            return null;
        }

        foreach ($alive as $tribute) {
            $tribute->leaveAlliance();
        }

        $names = TextFormatter::names($alive);

        return new EventResult(
            text: "Solo quedan {$names}. Solo puede haber un ganador.",
            notes: ['💔 La alianza se ha roto.'],
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

        $groupChance = $this->phase === EventPhase::Bloodbath ? self::BLOODBATH_GROUP_CHANCE : self::GROUP_EVENT_CHANCE;

        if ($roll <= $groupChance && $available >= 3) {
            $size = ($available >= 4 && $this->chance(50)) ? 4 : 3;
            $choice = $this->groupEvent($pending, $size);
        } elseif ($roll <= $groupChance + self::PAIR_EVENT_CHANCE + $this->modifier($pending[0], Personality::PAIR) && $available >= 2) {
            $choice = $this->allyAttackEvent($pending) ?? $this->pairEvent($pending[0], $pending[1]);
        }

        // Si no ha tocado evento de grupo o de pareja (o no había ninguno posible), individual
        return $choice ?? $this->soloEvent($pending[0]);
    }

    /**
     * Evento de 3 o 4 tributos. Nunca junta a aliados: en estos eventos todos
     * se hacen daño entre sí, y los aliados no se atacan.
     *
     * @param Tribute[] $pending
     */
    private function groupEvent(array $pending, int $size): ?array
    {
        $group = $this->groupWithoutAllies($pending, $size);

        // Si no hay 4 sin aliados entre ellos, quizá haya 3
        if ($group === null && $size === 4) {
            $group = $this->groupWithoutAllies($pending, 3);
        }

        if ($group === null) {
            return null;
        }

        $category = count($group) === 4 ? EventCategory::Quartet : EventCategory::Trio;
        $event = $this->pick($category, $group[0]);

        return $event !== null ? [$event, $group] : null;
    }

    /**
     * Va cogiendo tributos en orden, saltándose a los aliados de los que ya están.
     *
     * @param Tribute[] $candidates
     * @return Tribute[]|null null si no consigue reunir $size
     */
    private function groupWithoutAllies(array $candidates, int $size): ?array
    {
        $group = [];

        foreach ($candidates as $candidate) {
            foreach ($group as $member) {
                if ($member->isAllyOf($candidate)) {
                    continue 2;   // salta al siguiente candidato
                }
            }

            $group[] = $candidate;

            if (count($group) === $size) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Si el primero en espera tiene un aliado y hay alguien de fuera esperando,
     * puede que ataquen juntos: [atacante, aliado, víctima].
     *
     * @param Tribute[] $pending
     */
    private function allyAttackEvent(array $pending): ?array
    {
        $a = $pending[0];
        $ally = null;
        $victim = null;

        foreach (array_slice($pending, 1) as $tribute) {
            if ($a->isAllyOf($tribute)) {
                $ally ??= $tribute;
            } else {
                $victim ??= $tribute;
            }
        }

        // Primero se mira si es posible y solo entonces se tira el dado
        if ($ally === null || $victim === null || ! $this->chanceFor($a, Personality::ALLY_ATTACK, self::ALLY_ATTACK_CHANCE)) {
            return null;
        }

        $event = $this->pick(EventCategory::AllyAttack, $a);

        return $event !== null ? [$event, [$a, $ally, $victim]] : null;
    }

    private function pairEvent(Tribute $a, Tribute $b): ?array
    {
        if ($a->isAllyOf($b)) {
            $category = $this->chanceFor($a, Personality::BETRAYAL, self::BETRAYAL_CHANCE) ? EventCategory::Betrayal : EventCategory::Ally;
        } elseif ($this->canAlly($a, $b) && $this->friendshipChance($a, $b)) {
            $category = EventCategory::Befriend;
        } elseif ($a->bestItemOfType(ItemType::Weapon) !== null && $this->chanceFor($a, Personality::WEAPON, self::WEAPON_CHANCE)) {
            $category = EventCategory::Weapon;
        } else {
            $category = EventCategory::Duo;
        }

        $event = $this->pick($category, $a);

        // Si lleva un arma que no tiene eventos propios, pelea normal
        if ($event === null && $category === EventCategory::Weapon) {
            $event = $this->pick(EventCategory::Duo, $a);
        }

        return $event !== null ? [$event, [$a, $b]] : null;
    }

    private function soloEvent(Tribute $a): array
    {
        $discoveryChance = $this->phase === EventPhase::Bloodbath ? self::BLOODBATH_DISCOVERY_CHANCE : self::DISCOVERY_CHANCE;
        $category = $this->chanceFor($a, Personality::DISCOVERY, $discoveryChance) ? EventCategory::Discovery : EventCategory::Solo;

        $event = $this->pick($category, $a)
            ?? $this->pick(EventCategory::Solo, $a)
            ?? $this->pick(EventCategory::Discovery, $a)
            // Por si todos los eventos individuales fueran de otra fase
            ?? $this->pick(EventCategory::Solo, $a, anyPhase: true);

        if ($event === null) {
            throw new RuntimeException('No hay eventos individuales: revisa los seeders');
        }

        return [$event, [$a]];
    }

    /**
     * Pide un evento al EventPool con la fase actual, evitando los ya usados en la ronda.
     * Si el actor tiene eventos propios de su personalidad, hay un PERSONALITY_EVENT_CHANCE % de que salga uno.
     */
    private function pick(EventCategory $category, Tribute $actor, bool $anyPhase = false): ?GameEvent
    {
        $ownPersonality = $this->events->hasPersonalityEvents($category, $actor)
            && $this->chance(self::PERSONALITY_EVENT_CHANCE);

        return $this->events->pick(
            $category,
            $actor,
            $this->random,
            $anyPhase ? null : $this->phase,
            $this->usedThisRound,
            $ownPersonality,
        );
    }

    /**
     * ¿Pueden aliarse? Solo si la alianza que formarían no pasa del máximo
     * y no deja a TODOS los supervivientes en el mismo bando (se rompería en la ronda siguiente).
     */
    private function canAlly(Tribute $a, Tribute $b): bool
    {
        $size = count($a->alliance()) + count($b->alliance());

        return $size <= self::MAX_ALLIANCE_SIZE && $size < count($this->alive());
    }

    /** Lo que la personalidad del tributo suma o resta a una decisión (0 si es equilibrado) */
    private function modifier(Tribute $tribute, string $decision): int
    {
        return $tribute->personality?->modifier($decision) ?? 0;
    }

    /** Como chance(), pero con la probabilidad ajustada por la personalidad del tributo */
    private function chanceFor(Tribute $tribute, string $decision, int $percent): bool
    {
        return $this->chance(max(0, min(100, $percent + $this->modifier($tribute, $decision))));
    }

    /**
     * Hacerse aliados es cosa de dos: cuenta la media de las personalidades de ambos,
     * y si son del mismo distrito, más fácil todavía.
     */
    private function friendshipChance(Tribute $a, Tribute $b): bool
    {
        $modifier = intdiv($this->modifier($a, Personality::FRIENDSHIP) + $this->modifier($b, Personality::FRIENDSHIP), 2);

        if ($a->district->slug === $b->district->slug) {
            $modifier += self::DISTRICT_FRIENDSHIP_BONUS;
        }

        return $this->chance(max(0, min(100, self::FRIENDSHIP_CHANCE + $modifier)));
    }

    /** true con un $percent % de probabilidad */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }
}
