<?php

namespace Tests\Unit\Simulation;

use App\Enums\EventCategory;
use App\Enums\Gender;
use App\Enums\Terrain;
use App\Models\ArenaEvent;
use App\Models\District;
use App\Models\GameEvent;
use App\Simulation\EventPool;
use App\Simulation\Simulation;
use App\Simulation\Tribute;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tests\TestCase;

class TerrainTest extends TestCase
{
    private function tribute(string $name): Tribute
    {
        $district = new District(['slug' => 'test', 'name' => 'Distrito de prueba', 'strength_bonus' => 0, 'energy_bonus' => 0]);

        return new Tribute($name, Gender::Female, $district);
    }

    /** @return Tribute[] */
    private function tributes(): array
    {
        return array_map(fn (string $name) => $this->tribute($name), ['Avery', 'Dean', 'Hannah', 'Noah', 'Ezra', 'Luz']);
    }

    /** @param string[]|null $terrains */
    private function event(EventCategory $category, string $text, ?array $terrains = null, array $stats = []): GameEvent
    {
        $event = new GameEvent([
            'category' => $category,
            'text' => $text,
            'participants' => substr_count($text, '{'),
            'actor_hp' => 0, 'actor_energy' => 0, 'target_hp' => 0, 'target_energy' => 0,
            'terrains' => $terrains,
            ...$stats,
        ]);
        $event->setRelation('requiresItem', null);
        $event->setRelation('givesItem', null);

        return $event;
    }

    /** @param string[]|null $terrains */
    private function catastrophe(string $slug, ?array $terrains): GameEvent
    {
        $arena = new ArenaEvent(['slug' => $slug, 'name' => ucfirst($slug), 'intro' => '...', 'terrains' => $terrains]);
        $event = $this->event(EventCategory::Arena, "[{$slug}] {A}");
        $event->setRelation('arenaEvent', $arena);

        return $event;
    }

    /** Un evento de una persona de cada tipo de arena, y uno que vale para todas */
    private function events(): array
    {
        return [
            $this->event(EventCategory::Solo, '[todas] {A}'),
            $this->event(EventCategory::Solo, '[ciudad] {A}', ['city']),
            $this->event(EventCategory::Solo, '[ciudad y desierto] {A}', ['city', 'desert']),
            $this->event(EventCategory::Solo, '[desierto] {A}', ['desert']),
            $this->event(EventCategory::Duo, '[duo] {A} {B}'),
            $this->catastrophe('inundacion', ['city']),
            $this->catastrophe('tormenta_arena', ['desert']),
        ];
    }

    /** Qué textos de una persona pueden salir en esa arena (probando muchas veces) */
    private function soloTextsIn(Terrain $terrain): array
    {
        $pool = new EventPool($this->events(), $terrain);
        $random = new Randomizer(new Xoshiro256StarStar(1));
        $texts = [];

        for ($i = 0; $i < 300; $i++) {
            $texts[$pool->pick(EventCategory::Solo, $this->tribute('Avery'), $random)->text] = true;
        }
        ksort($texts);

        return array_keys($texts);
    }

    public function test_each_terrain_only_has_its_own_events_and_the_general_ones(): void
    {
        $this->assertSame(['[ciudad y desierto] {A}', '[ciudad] {A}', '[todas] {A}'], $this->soloTextsIn(Terrain::City));
        $this->assertSame(['[ciudad y desierto] {A}', '[desierto] {A}', '[todas] {A}'], $this->soloTextsIn(Terrain::Desert));
        $this->assertSame(['[todas] {A}'], $this->soloTextsIn(Terrain::Peaks));
    }

    public function test_each_terrain_only_has_its_own_catastrophes(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(1));

        $this->assertSame('inundacion', (new EventPool($this->events(), Terrain::City))->pickArena($random)->slug);
        $this->assertSame('tormenta_arena', (new EventPool($this->events(), Terrain::Desert))->pickArena($random)->slug);
        $this->assertFalse((new EventPool($this->events(), Terrain::Swamp))->hasArenas());
    }

    public function test_themed_terrains_favour_their_own_events(): void
    {
        // Muchos eventos generales y uno solo del desierto: aun así tiene que salir bastante
        $events = [$this->event(EventCategory::Solo, '[desierto] {A}', ['desert'])];
        foreach (range(1, 20) as $i) {
            $events[] = $this->event(EventCategory::Solo, "[general {$i}] {A}");
        }

        $count = function (Terrain $terrain) use ($events) {
            $pool = new EventPool($events, $terrain);
            $random = new Randomizer(new Xoshiro256StarStar(7));
            $own = 0;

            for ($i = 0; $i < 1000; $i++) {
                $own += $pool->pick(EventCategory::Solo, $this->tribute('Avery'), $random)->text === '[desierto] {A}' ? 1 : 0;
            }

            return $own;
        };

        // Al azar, 1 de cada 21 (unas 48 de 1000); con la preferencia, casi la mitad
        $this->assertGreaterThan(350, $count(Terrain::Desert));
    }

    public function test_city_games_are_the_same_as_before_there_were_terrains(): void
    {
        // Con los eventos del desierto en la base de datos o sin ellos, una partida en la ciudad sale igual
        $cityOnly = array_values(array_filter($this->events(), fn (GameEvent $e) => $e->terrains === null || in_array('city', $e->terrains, true)));

        $play = function (array $events) {
            $simulation = new Simulation($this->tributes(), new EventPool($events, Terrain::City), 42);
            $texts = [];

            for ($i = 0; $i < 10 && ! $simulation->isOver(); $i++) {
                foreach ($simulation->runRound() as $result) {
                    $texts[] = $result->text;
                }
            }

            return $texts;
        };

        $this->assertSame($play($cityOnly), $play($this->events()));
    }

    public function test_the_city_changes_nothing_and_the_others_change_some_decisions(): void
    {
        foreach (['pair', 'friendship', 'betrayal', 'weapon', 'discovery', 'ally_attack'] as $decision) {
            $this->assertSame(0, Terrain::City->modifier($decision));
        }

        $this->assertLessThan(0, Terrain::Desert->modifier('discovery'));
        $this->assertGreaterThan(0, Terrain::Peaks->modifier('friendship'));
        $this->assertNull(Terrain::City->intro());
        $this->assertNotNull(Terrain::Swamp->intro());
    }
}
