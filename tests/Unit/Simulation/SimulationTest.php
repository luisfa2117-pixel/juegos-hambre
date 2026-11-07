<?php

namespace Tests\Unit\Simulation;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use App\Enums\Gender;
use App\Enums\ItemType;
use App\Enums\Personality;
use App\Models\ArenaEvent;
use App\Models\District;
use App\Models\GameEvent;
use App\Models\Item;
use App\Simulation\EventPool;
use App\Simulation\EventResult;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use LogicException;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    private const NAMES = ['Dean', 'Avery', 'De Santi', 'Hannah', 'Desiree', 'Noah', 'Avril', 'Horacio'];

    // ---- Ayudantes ----

    private function tribute(string $name, ?Personality $personality = null): Tribute
    {
        $district = new District([
            'slug' => 'test', 'name' => 'Distrito de prueba', 'strength_bonus' => 0, 'energy_bonus' => 0,
        ]);

        return new Tribute($name, Gender::Male, $district, $personality);
    }

    /** @return Tribute[] */
    private function tributes(int $count = 8): array
    {
        return array_map(fn (string $name) => $this->tribute($name), array_slice(self::NAMES, 0, $count));
    }

    private function knife(): Item
    {
        return new Item(['slug' => 'cuchillo', 'name' => 'Cuchillo', 'type' => ItemType::Weapon, 'power' => 5]);
    }

    private function event(EventCategory $category, string $text, array $stats = [], ?Item $requires = null, ?Item $gives = null): GameEvent
    {
        $event = new GameEvent(array_merge([
            'category' => $category,
            'text' => $text,
            'participants' => substr_count($text, '{'),
            'actor_hp' => 0, 'actor_energy' => 0, 'target_hp' => 0, 'target_energy' => 0,
        ], $stats));

        $event->setRelation('requiresItem', $requires);
        $event->setRelation('givesItem', $gives);

        return $event;
    }

    /**
     * Un evento por categoría que no hace nada. Cada texto empieza por su categoría
     * entre corchetes, para saber en los tests qué tipo de evento ha salido.
     */
    private function harmlessPool(): EventPool
    {
        return new EventPool([
            $this->event(EventCategory::Solo, '[solo] {A}'),
            $this->event(EventCategory::Discovery, '[discovery] {A}'),
            $this->event(EventCategory::Duo, '[duo] {A} {B}'),
            $this->event(EventCategory::Trio, '[trio] {A} {B} {C}'),
            $this->event(EventCategory::Quartet, '[quartet] {A} {B} {C} {D}'),
            $this->event(EventCategory::Ally, '[ally] {A} {B}'),
            $this->event(EventCategory::Befriend, '[befriend] {A} {B}'),
            $this->event(EventCategory::Betrayal, '[betrayal] {A} {B}'),
            $this->event(EventCategory::Weapon, '[weapon] {A} {B}', requires: $this->knife()),
            $this->event(EventCategory::AllyAttack, '[ally_attack] {A} {B} {C}'),
        ]);
    }

    /** Eventos que hacen daño de verdad, para jugar partidas completas. */
    private function deadlyPool(): EventPool
    {
        $knife = $this->knife();

        return new EventPool([
            $this->event(EventCategory::Solo, '{A} pisó una mina', ['actor_hp' => -4, 'actor_energy' => -2]),
            $this->event(EventCategory::Discovery, '{A} encontró un cuchillo', gives: $knife),
            $this->event(EventCategory::Duo, '{A} disparó a {B}', ['target_hp' => -8, 'actor_energy' => -2]),
            $this->event(EventCategory::Trio, '{A} lanzó una bomba a {B} y {C}', ['target_hp' => -7]),
            $this->event(EventCategory::Quartet, '{A}, {B}, {C} y {D} cayeron en una trampa', ['actor_hp' => -5, 'target_hp' => -5]),
            $this->event(EventCategory::Ally, '{A} y {B} descansaron', ['actor_hp' => 2, 'target_hp' => 2]),
            $this->event(EventCategory::Befriend, '{A} y {B} acordaron una tregua'),
            $this->event(EventCategory::Betrayal, '{A} traicionó a {B}', ['target_hp' => -12]),
            $this->event(EventCategory::Weapon, '{A} apuñaló a {B}', ['target_hp' => -10], requires: $knife),
            $this->event(EventCategory::AllyAttack, '{A} y {B} emboscaron a {C}', ['actor_energy' => -2, 'target_hp' => -9]),
        ]);
    }

    private function simulation(array $tributes, EventPool $pool, int $seed): Simulation
    {
        return new Simulation($tributes, $pool, $seed);
    }

    /** Juega una partida entera y devuelve todos los textos (con un límite por si acaso). */
    private function playFullGame(int $seed): array
    {
        $simulation = $this->simulation($this->tributes(), $this->deadlyPool(), $seed);
        $texts = [];

        while (! $simulation->isOver() && $simulation->round() < 200) {
            foreach ($simulation->runRound() as $result) {
                $texts[] = $result->text;
            }
        }

        return $texts;
    }

    // ---- Tests ----

    public function test_every_alive_tribute_takes_part_exactly_once_per_round(): void
    {
        foreach (range(1, 30) as $seed) {
            $simulation = $this->simulation($this->tributes(), $this->harmlessPool(), $seed);

            $participants = [];
            foreach ($simulation->runRound() as $result) {
                array_push($participants, ...$result->participants);
            }

            sort($participants);
            $expected = self::NAMES;
            sort($expected);
            $this->assertSame($expected, $participants, "Semilla {$seed}");
        }
    }

    public function test_same_seed_produces_the_same_game(): void
    {
        $this->assertSame($this->playFullGame(42), $this->playFullGame(42));
    }

    public function test_game_ends_with_at_most_one_survivor_and_the_dead_stay_dead(): void
    {
        foreach (range(1, 10) as $seed) {
            $simulation = $this->simulation($this->tributes(), $this->deadlyPool(), $seed);
            $previousAlive = 8;

            while (! $simulation->isOver() && $simulation->round() < 200) {
                $simulation->runRound();
                $alive = count($simulation->alive());

                $this->assertLessThanOrEqual($previousAlive, $alive, "Semilla {$seed}: alguien ha resucitado");
                $previousAlive = $alive;
            }

            $this->assertTrue($simulation->isOver(), "Semilla {$seed}: la partida no ha terminado");
            $this->assertLessThanOrEqual(1, count($simulation->alive()));
        }
    }

    public function test_allies_never_get_stranger_events(): void
    {
        foreach (range(1, 50) as $seed) {
            // Tres tributos: con solo dos aliados, la alianza se rompería al empezar (ver más abajo)
            [$dean, $avery, $hannah] = $this->tributes(3);
            $dean->befriend($avery);
            $simulation = $this->simulation([$dean, $avery, $hannah], $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $names = $result->participants;
                sort($names);

                if ($names === ['Avery', 'Dean']) {
                    $this->assertStringStartsNotWith('[duo]', $result->text);
                    $this->assertStringStartsNotWith('[befriend]', $result->text);
                    $this->assertStringStartsNotWith('[weapon]', $result->text);
                }
            }
        }
    }

    public function test_weapon_events_only_happen_with_a_weapon(): void
    {
        foreach (range(1, 50) as $seed) {
            $simulation = $this->simulation($this->tributes(4), $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $this->assertStringStartsNotWith('[weapon]', $result->text);
            }
        }
    }

    public function test_tribute_with_a_weapon_eventually_uses_it(): void
    {
        $used = false;

        foreach (range(1, 50) as $seed) {
            [$dean, $avery] = $this->tributes(2);
            $dean->addItem($this->knife());
            $avery->addItem($this->knife());
            $simulation = $this->simulation([$dean, $avery], $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $used = $used || str_starts_with($result->text, '[weapon]');
            }
        }

        $this->assertTrue($used);
    }

    public function test_alliance_dissolves_when_only_its_members_remain(): void
    {
        [$dean, $avery, $deSanti, $hannah] = $this->tributes(4);
        $dean->befriend($avery);
        $dean->befriend($deSanti);
        $hannah->changeHp(-20);
        $simulation = $this->simulation([$dean, $avery, $deSanti, $hannah], $this->harmlessPool(), 1);

        $results = $simulation->runRound();

        $this->assertSame('Solo quedan Dean, Avery y De Santi. Solo puede haber un ganador.', $results[0]->text);
    }

    public function test_alliance_breaks_when_only_two_allies_remain(): void
    {
        [$dean, $avery, $hannah] = $this->tributes(3);
        $dean->befriend($avery);
        $hannah->changeHp(-20);
        $simulation = $this->simulation([$dean, $avery, $hannah], $this->harmlessPool(), 1);

        $results = $simulation->runRound();

        // Lo primero que pasa en la ronda es la ruptura
        $this->assertStringStartsWith('Solo quedan', $results[0]->text);
        $this->assertStringContainsString('se ha roto', $results[0]->notes[0]);

        // Y después del anuncio ya no se tratan como aliados
        foreach (array_slice($results, 1) as $result) {
            $this->assertStringStartsNotWith('[ally]', $result->text);
            $this->assertStringStartsNotWith('[betrayal]', $result->text);
        }
    }

    public function test_allies_are_never_in_the_same_group_event(): void
    {
        foreach (range(1, 50) as $seed) {
            $tributes = $this->tributes();
            [$dean, $avery, $deSanti] = $tributes;
            $dean->befriend($avery);
            $dean->befriend($deSanti);
            $simulation = $this->simulation($tributes, $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                // Atacar juntos es justo lo que SÍ pueden hacer los aliados
                if (str_starts_with($result->text, '[ally_attack]')) {
                    continue;
                }

                if (count($result->participants) >= 3) {
                    $allies = array_intersect($result->participants, ['Dean', 'Avery', 'De Santi']);
                    $this->assertLessThanOrEqual(1, count($allies), "Semilla {$seed}: {$result->text}");
                }
            }
        }
    }

    public function test_alliances_never_grow_beyond_the_maximum(): void
    {
        foreach (range(1, 20) as $seed) {
            $simulation = $this->simulation($this->tributes(), $this->harmlessPool(), $seed);

            for ($round = 0; $round < 15; $round++) {
                $simulation->runRound();

                foreach ($simulation->tributes() as $tribute) {
                    $this->assertLessThanOrEqual(Simulation::MAX_ALLIANCE_SIZE, count($tribute->alliance()));
                }
            }
        }
    }

    public function test_in_ally_attacks_the_first_two_are_allies_and_the_third_is_not(): void
    {
        $seen = 0;

        foreach (range(1, 60) as $seed) {
            $tributes = $this->tributes(6);
            $tributes[0]->befriend($tributes[1]);
            $tributes[2]->befriend($tributes[3]);
            $simulation = $this->simulation($tributes, $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                if (! str_starts_with($result->text, '[ally_attack]')) {
                    continue;
                }

                $seen++;
                [$a, $b, $c] = array_map(
                    fn (string $name) => collect($tributes)->firstWhere('name', $name),
                    $result->participants,
                );
                $this->assertTrue($a->isAllyOf($b), $result->text);
                $this->assertFalse($a->isAllyOf($c), $result->text);
            }
        }

        $this->assertGreaterThan(0, $seen, 'Ningún ataque conjunto en 60 partidas');
    }

    public function test_first_round_is_the_bloodbath_and_then_day_and_night_alternate(): void
    {
        $simulation = $this->simulation($this->tributes(), $this->harmlessPool(), 1);
        $phases = [];

        for ($i = 0; $i < 5; $i++) {
            $simulation->runRound();
            $phases[] = $simulation->phase();
        }

        $this->assertSame(
            [EventPhase::Bloodbath, EventPhase::Day, EventPhase::Night, EventPhase::Day, EventPhase::Night],
            $phases,
        );
    }

    public function test_events_only_happen_in_their_phase(): void
    {
        $pool = new EventPool([
            $this->event(EventCategory::Solo, '[cualquiera] {A}'),
            $this->event(EventCategory::Solo, '[noche] {A}', ['phase' => EventPhase::Night]),
            $this->event(EventCategory::Solo, '[cornucopia] {A}', ['phase' => EventPhase::Bloodbath]),
        ]);

        foreach (range(1, 20) as $seed) {
            $simulation = $this->simulation($this->tributes(), $pool, $seed);

            for ($i = 0; $i < 5; $i++) {
                foreach ($simulation->runRound() as $result) {
                    $phase = $simulation->phase();

                    if ($phase === EventPhase::Bloodbath) {
                        // Hay evento de la Cornucopia, así que en el baño de sangre solo sale ese
                        $this->assertStringStartsWith('[cornucopia]', $result->text);
                    } else {
                        $this->assertStringStartsNotWith('[cornucopia]', $result->text);
                    }

                    if ($phase === EventPhase::Day) {
                        $this->assertStringStartsNotWith('[noche]', $result->text);
                    }
                }
            }
        }
    }

    public function test_bloodbath_falls_back_to_any_time_events(): void
    {
        $pool = new EventPool([
            $this->event(EventCategory::Solo, '[cualquiera] {A}'),
            $this->event(EventCategory::Solo, '[noche] {A}', ['phase' => EventPhase::Night]),
        ]);
        $simulation = $this->simulation($this->tributes(2), $pool, 3);

        foreach ($simulation->runRound() as $result) {
            $this->assertStringStartsWith('[cualquiera]', $result->text);
        }
    }

    public function test_an_event_does_not_repeat_in_the_same_round_while_others_remain(): void
    {
        $pool = new EventPool([
            $this->event(EventCategory::Solo, '[uno] {A}'),
            $this->event(EventCategory::Solo, '[dos] {A}'),
            $this->event(EventCategory::Solo, '[tres] {A}'),
        ]);

        foreach (range(1, 30) as $seed) {
            // Tres tributos y tres eventos: en cada ronda tiene que salir cada uno una vez
            $simulation = $this->simulation($this->tributes(3), $pool, $seed);
            $texts = array_map(fn ($result) => strtok($result->text, ' '), $simulation->runRound());

            sort($texts);
            $this->assertSame(['[dos]', '[tres]', '[uno]'], $texts, "Semilla {$seed}");
        }
    }

    public function test_events_can_repeat_when_there_are_no_others(): void
    {
        $pool = new EventPool([$this->event(EventCategory::Solo, '[único] {A}')]);
        $simulation = $this->simulation($this->tributes(4), $pool, 1);

        $this->assertCount(4, $simulation->runRound());
    }

    /** Eventos normales que no hacen nada más una catástrofe con dos eventos */
    private function poolWithArena(): EventPool
    {
        $arena = new ArenaEvent(['slug' => 'inundacion', 'name' => 'Inundación', 'intro' => 'Sube el agua.']);
        $arenaEvent = function (string $text) use ($arena) {
            $event = $this->event(EventCategory::Arena, $text);
            $event->setRelation('arenaEvent', $arena);

            return $event;
        };

        return new EventPool([
            $this->event(EventCategory::Solo, '[solo] {A}'),
            $this->event(EventCategory::Duo, '[duo] {A} {B}'),
            $arenaEvent('[arena] {A} subió a un tejado'),
            $arenaEvent('[arena] {A} nadó'),
        ]);
    }

    public function test_catastrophes_hit_every_alive_tribute_once_and_respect_their_timing(): void
    {
        $seen = 0;

        foreach (range(1, 40) as $seed) {
            $simulation = $this->simulation($this->tributes(), $this->poolWithArena(), $seed);
            $arenaRounds = [];

            for ($i = 0; $i < 16; $i++) {
                $aliveBefore = array_map(fn (Tribute $t) => $t->name, $simulation->alive());
                $results = $simulation->runRound();

                if ($results[0]->arena === null) {
                    continue;
                }

                $seen++;
                $arenaRounds[] = $simulation->round();
                $this->assertSame('Inundación', $results[0]->arena);
                $this->assertGreaterThanOrEqual(Simulation::ARENA_FIRST_ROUND, $simulation->round());

                // Después del anuncio, un evento de la catástrofe por cada tributo vivo
                $participants = [];
                foreach (array_slice($results, 1) as $result) {
                    $this->assertStringStartsWith('[arena]', $result->text);
                    array_push($participants, ...$result->participants);
                }
                sort($participants);
                sort($aliveBefore);
                $this->assertSame($aliveBefore, $participants);
            }

            // Nunca dos catástrofes demasiado seguidas
            for ($j = 1; $j < count($arenaRounds); $j++) {
                $this->assertGreaterThanOrEqual(Simulation::ARENA_COOLDOWN, $arenaRounds[$j] - $arenaRounds[$j - 1]);
            }
        }

        $this->assertGreaterThan(0, $seen, 'Ninguna catástrofe en 40 partidas');
    }

    public function test_no_catastrophes_without_arena_events(): void
    {
        $simulation = $this->simulation($this->tributes(), $this->harmlessPool(), 1);

        for ($i = 0; $i < 12; $i++) {
            $this->assertNull($simulation->runRound()[0]->arena);
        }
    }

    // ---- Personalidades ----

    /**
     * Juega muchas rondas sueltas (una por semilla) y cuenta cuántos eventos empiezan por $prefix.
     * $setup recibe los tributos recién creados para prepararlos (aliarlos, por ejemplo).
     */
    private function countEvents(string $prefix, ?Personality $personality, ?callable $setup = null, int $seeds = 300): int
    {
        $count = 0;

        foreach (range(1, $seeds) as $seed) {
            $tributes = [
                $this->tribute('Dean', $personality),
                $this->tribute('Avery', $personality),
                $this->tribute('Noah', $personality),
            ];

            if ($setup !== null) {
                $setup($tributes);
            }

            $simulation = $this->simulation($tributes, $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $count += str_starts_with($result->text, $prefix) ? 1 : 0;
            }
        }

        return $count;
    }

    public function test_loyal_tributes_never_betray(): void
    {
        $allies = fn (array $t) => $t[0]->befriend($t[1]);

        $this->assertGreaterThan(0, $this->countEvents('[betrayal]', null, $allies));
        $this->assertSame(0, $this->countEvents('[betrayal]', Personality::Loyal, $allies));
    }

    public function test_treacherous_tributes_betray_more(): void
    {
        $allies = fn (array $t) => $t[0]->befriend($t[1]);

        $balanced = $this->countEvents('[betrayal]', null, $allies);
        $treacherous = $this->countEvents('[betrayal]', Personality::Treacherous, $allies);

        $this->assertGreaterThan($balanced * 1.5, $treacherous);
    }

    public function test_aggressive_tributes_make_fewer_alliances(): void
    {
        $balanced = $this->countEvents('[befriend]', null);
        $aggressive = $this->countEvents('[befriend]', Personality::Aggressive);

        $this->assertLessThan($balanced, $aggressive);
    }

    public function test_sociable_tributes_make_more_alliances(): void
    {
        $balanced = $this->countEvents('[befriend]', null);
        $sociable = $this->countEvents('[befriend]', Personality::Sociable);

        $this->assertGreaterThan($balanced, $sociable);
    }

    public function test_resourceful_tributes_find_more_items(): void
    {
        $balanced = $this->countEvents('[discovery]', null);
        $resourceful = $this->countEvents('[discovery]', Personality::Resourceful);

        $this->assertGreaterThan($balanced, $resourceful);
    }

    public function test_cowards_get_into_fewer_pair_events(): void
    {
        $pairs = fn (?Personality $p) => $this->countEvents('[duo]', $p) + $this->countEvents('[befriend]', $p);

        $this->assertLessThan($pairs(null), $pairs(Personality::Coward));
    }

    public function test_personality_events_only_happen_to_that_personality(): void
    {
        $pool = new EventPool([
            $this->event(EventCategory::Solo, '[normal] {A}'),
            $this->event(EventCategory::Solo, '[otro normal] {A}'),
            $this->event(EventCategory::Solo, '[cobarde] {A}', ['personality' => Personality::Coward]),
        ]);
        $count = function (?Personality $personality) use ($pool) {
            $own = 0;
            $total = 0;

            foreach (range(1, 100) as $seed) {
                $tributes = [$this->tribute('Dean', $personality), $this->tribute('Avery', $personality)];

                foreach ($this->simulation($tributes, $pool, $seed)->runRound() as $result) {
                    $total++;
                    $own += str_starts_with($result->text, '[cobarde]') ? 1 : 0;
                }
            }

            return [$own, $total];
        };

        [$own] = $count(null);
        $this->assertSame(0, $own, 'Un tributo equilibrado ha protagonizado un evento de cobarde');

        // Para los cobardes sale bastante más que 1 de cada 3 (lo que tocaría si fuera un evento más)
        [$own, $total] = $count(Personality::Coward);
        $this->assertGreaterThan($total / 3, $own);
    }

    public function test_nobody_allies_when_only_two_remain(): void
    {
        foreach (range(1, 100) as $seed) {
            $simulation = $this->simulation($this->tributes(2), $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $this->assertStringStartsNotWith('[befriend]', $result->text);
            }
        }
    }

    public function test_nobody_joins_an_alliance_that_would_include_every_survivor(): void
    {
        foreach (range(1, 100) as $seed) {
            [$dean, $avery, $noah] = $this->tributes(3);
            $dean->befriend($avery);
            $simulation = $this->simulation([$dean, $avery, $noah], $this->harmlessPool(), $seed);

            foreach ($simulation->runRound() as $result) {
                $this->assertStringStartsNotWith('[befriend]', $result->text);
            }
        }
    }

    public function test_district_partners_ally_more_often(): void
    {
        $count = function (bool $sameDistrict) {
            $alliances = 0;

            foreach (range(1, 300) as $seed) {
                $tributes = array_map(function (string $name, int $i) use ($sameDistrict) {
                    $slug = $sameDistrict ? 'mismo' : "distrito-{$i}";
                    $district = new District(['slug' => $slug, 'name' => $slug, 'strength_bonus' => 0, 'energy_bonus' => 0]);

                    return new Tribute($name, Gender::Male, $district);
                }, ['Dean', 'Avery', 'Noah'], [0, 1, 2]);

                foreach ($this->simulation($tributes, $this->harmlessPool(), $seed)->runRound() as $result) {
                    $alliances += str_starts_with($result->text, '[befriend]') ? 1 : 0;
                }
            }

            return $alliances;
        };

        $this->assertGreaterThan($count(false), $count(true));
    }

    // ---- Guardar y continuar ----

    /** Todos los textos ronda a ronda: [1 => [...], 2 => [...], ...] */
    private function playToEnd(Simulation $simulation): array
    {
        $rounds = [];

        while (! $simulation->isOver() && $simulation->round() < 200) {
            $rounds[$simulation->round() + 1] = array_map(fn ($result) => $result->text, $simulation->runRound());
        }

        return $rounds;
    }

    public function test_a_resumed_game_continues_exactly_as_the_original(): void
    {
        $knife = $this->knife();
        $districts = ['test' => $this->tribute('x')->district];

        foreach (range(1, 15) as $seed) {
            $tributes = [
                $this->tribute('Dean', Personality::Aggressive),
                $this->tribute('Avery', Personality::Loyal),
                $this->tribute('De Santi'),
                $this->tribute('Hannah', Personality::Treacherous),
                $this->tribute('Desiree', Personality::Sociable),
                $this->tribute('Noah', Personality::Coward),
            ];
            $original = $this->simulation($tributes, $this->deadlyPool(), $seed);

            // Jugamos 3 rondas, guardamos, y seguimos hasta el final
            $before = [];
            for ($i = 0; $i < 3 && ! $original->isOver(); $i++) {
                $before[] = $original->runRound();
            }
            $state = $original->toState();
            $rest = $this->playToEnd($original);

            // Otra partida reconstruida desde lo guardado tiene que hacer exactamente lo mismo
            $resumed = Simulation::fromState($state, $this->deadlyPool(), $seed, $districts, ['cuchillo' => $knife]);

            $this->assertSame($rest, $this->playToEnd($resumed), "Semilla {$seed}");
        }
    }

    public function test_state_keeps_alliances_in_order(): void
    {
        [$dean, $avery, $noah, $hannah] = $this->tributes(4);
        $dean->befriend($avery);
        $dean->befriend($noah);
        $simulation = $this->simulation([$dean, $avery, $noah, $hannah], $this->harmlessPool(), 1);

        $resumed = Simulation::fromState($simulation->toState(), $this->harmlessPool(), 1, ['test' => $dean->district], []);

        $this->assertSame($simulation->toState(), $resumed->toState());
        [$rDean, $rAvery, $rNoah, $rHannah] = $resumed->tributes();
        $this->assertTrue($rNoah->isAllyOf($rAvery));
        $this->assertFalse($rHannah->isAllyOf($rDean));
    }

    public function test_each_round_has_its_own_randomness(): void
    {
        // Con la misma semilla, la ronda 5 sale igual llegue como llegue: solo depende de la semilla y del número
        $a = $this->simulation($this->tributes(), $this->harmlessPool(), 42);
        $b = $this->simulation($this->tributes(), $this->harmlessPool(), 42);
        $c = $this->simulation($this->tributes(), $this->harmlessPool(), 43);

        $textsA = $this->playToEnd($a);

        $this->assertSame(array_slice($textsA, 0, 3), array_slice($this->playToEnd($b), 0, 3));
        $this->assertNotSame(array_slice($textsA, 0, 3), array_slice($this->playToEnd($c), 0, 3));
    }

    public function test_announcements_open_the_next_round_only(): void
    {
        $simulation = $this->simulation($this->tributes(4), $this->harmlessPool(), 1);
        $simulation->runRound();

        $simulation->announce(new EventResult(text: 'Un patrocinador ha enviado algo', gift: true));
        $next = $simulation->runRound();
        $after = $simulation->runRound();

        $this->assertSame('Un patrocinador ha enviado algo', $next[0]->text);
        $this->assertTrue($next[0]->gift);
        $this->assertFalse($after[0]->gift);
    }

    public function test_last_survivor_is_the_winner_and_no_more_rounds_can_be_played(): void
    {
        [$dean, $avery] = $this->tributes(2);
        $avery->changeHp(-20);
        $simulation = $this->simulation([$dean, $avery], $this->harmlessPool(), 1);

        $this->assertTrue($simulation->isOver());
        $this->assertSame($dean, $simulation->winner());

        $this->expectException(LogicException::class);
        $simulation->runRound();
    }
}
