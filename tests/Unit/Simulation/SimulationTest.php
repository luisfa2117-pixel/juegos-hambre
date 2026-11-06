<?php

namespace Tests\Unit\Simulation;

use App\Enums\EventCategory;
use App\Enums\Gender;
use App\Enums\ItemType;
use App\Models\District;
use App\Models\GameEvent;
use App\Models\Item;
use App\Simulation\EventPool;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use LogicException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    private const NAMES = ['Dean', 'Avery', 'De Santi', 'Hannah', 'Desiree', 'Noah', 'Avril', 'Horacio'];

    // ---- Ayudantes ----

    private function tribute(string $name): Tribute
    {
        $district = new District([
            'slug' => 'test', 'name' => 'Distrito de prueba', 'strength_bonus' => 0, 'energy_bonus' => 0,
        ]);

        return new Tribute($name, Gender::Male, $district);
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
        return new Simulation($tributes, $pool, new Randomizer(new Mt19937($seed)));
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
