<?php

namespace Tests\Unit\Simulation;

use App\Enums\Gender;
use App\Models\District;
use App\Simulation\TextFormatter;
use App\Simulation\Tribute;
use Tests\TestCase;

class TextFormatterTest extends TestCase
{
    private function male(string $name): Tribute
    {
        return new Tribute($name, Gender::Male, $this->district());
    }

    private function female(string $name): Tribute
    {
        return new Tribute($name, Gender::Female, $this->district());
    }

    private function district(): District
    {
        return new District(['slug' => 'test', 'name' => 'Test', 'strength_bonus' => 0, 'energy_bonus' => 0]);
    }

    public function test_replaces_names(): void
    {
        $text = TextFormatter::format('{A} disparó a {B}', [$this->male('Dean'), $this->female('Avery')]);

        $this->assertSame('Dean disparó a Avery', $text);
    }

    public function test_single_marker_follows_that_tributes_gender(): void
    {
        $text = '{A} fue herid{A:o/a}';

        $this->assertSame('Dean fue herido', TextFormatter::format($text, [$this->male('Dean')]));
        $this->assertSame('Avery fue herida', TextFormatter::format($text, [$this->female('Avery')]));
    }

    public function test_marker_can_refer_to_a_target(): void
    {
        $text = TextFormatter::format(
            '{A} cegó a {B} y l{B:o/a} apuñaló',
            [$this->male('Dean'), $this->female('Avery')],
        );

        $this->assertSame('Dean cegó a Avery y la apuñaló', $text);
    }

    public function test_plural_is_feminine_only_if_all_are_women(): void
    {
        $text = '{A} y {B} asaltaron junt{AB:os/as} un puesto';

        $this->assertSame(
            'Avery y Avril asaltaron juntas un puesto',
            TextFormatter::format($text, [$this->female('Avery'), $this->female('Avril')]),
        );
        $this->assertSame(
            'Avery y Noah asaltaron juntos un puesto',
            TextFormatter::format($text, [$this->female('Avery'), $this->male('Noah')]),
        );
    }

    public function test_joins_names_naturally(): void
    {
        $this->assertSame('Dean', TextFormatter::names([$this->male('Dean')]));
        $this->assertSame('Dean y Avery', TextFormatter::names([$this->male('Dean'), $this->female('Avery')]));
        $this->assertSame(
            'Dean, Avery y Noah',
            TextFormatter::names([$this->male('Dean'), $this->female('Avery'), $this->male('Noah')]),
        );
    }
}
