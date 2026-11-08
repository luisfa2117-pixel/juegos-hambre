<?php

namespace Tests\Unit\Simulation;

use App\Enums\GameRule;
use App\Enums\Personality;
use App\Simulation\GameRules;
use Tests\TestCase;

class GameRulesTest extends TestCase
{
    public function test_rules_are_kept_in_a_fixed_order_without_duplicates(): void
    {
        $rules = new GameRules([GameRule::Scarcity, GameRule::Armed, GameRule::Scarcity]);

        $this->assertSame(['armed', 'scarcity'], $rules->values());
    }

    public function test_rules_are_rebuilt_from_the_database_ignoring_unknown_values(): void
    {
        $rules = GameRules::fromValues(['half_life', 'una_regla_que_ya_no_existe']);

        $this->assertTrue($rules->has(GameRule::HalfLife));
        $this->assertSame(['half_life'], $rules->values());
        $this->assertTrue(GameRules::fromValues(null)->isEmpty());
    }

    public function test_modifiers_of_every_rule_are_added_up(): void
    {
        $rules = new GameRules([GameRule::Traitors, GameRule::Scarcity]);

        $this->assertSame(40, $rules->modifier(Personality::BETRAYAL));
        $this->assertSame(-100, $rules->modifier(Personality::DISCOVERY));
        $this->assertSame(0, $rules->modifier(Personality::WEAPON));
    }

    public function test_no_alliances_and_traitors_conflict(): void
    {
        $this->assertTrue(GameRule::NoAlliances->conflictsWith(GameRule::Traitors));
        $this->assertTrue(GameRule::Traitors->conflictsWith(GameRule::NoAlliances));
        $this->assertFalse(GameRule::Armed->conflictsWith(GameRule::HalfLife));
    }

    public function test_the_quell_picks_two_compatible_rules_and_always_the_same_for_a_seed(): void
    {
        $combinations = [];

        foreach (range(1, 200) as $seed) {
            $rules = GameRule::quell($seed);

            $this->assertCount(2, $rules);
            $this->assertFalse($rules[0]->conflictsWith($rules[1]), "Semilla {$seed}");
            $this->assertSame($rules, GameRule::quell($seed));

            $combinations[(new GameRules($rules))->values()[0].'+'.(new GameRules($rules))->values()[1]] = true;
        }

        // 6 reglas dan 15 parejas, menos la que no se puede: con 200 semillas tienen que salir todas
        $this->assertCount(14, $combinations);
    }

    public function test_the_announcement_lists_the_rules(): void
    {
        $this->assertNull((new GameRules)->announcement());

        $announcement = (new GameRules([GameRule::Armed]))->announcement(quell: true);

        $this->assertSame('Vasallaje de los 25', $announcement->arena);
        $this->assertStringContainsString(GameRule::Armed->description(), $announcement->text);
    }
}
