<?php

namespace Tests\Unit\Simulation;

use App\Enums\Gender;
use App\Enums\ItemType;
use App\Models\District;
use App\Models\Item;
use App\Simulation\Tribute;
use Tests\TestCase;

class TributeTest extends TestCase
{
    // ---- Ayudantes para crear datos de prueba sin base de datos ----

    private function district(int $strength = 0, int $energy = 0): District
    {
        return new District([
            'slug' => 'test', 'name' => 'Distrito de prueba',
            'strength_bonus' => $strength, 'energy_bonus' => $energy,
        ]);
    }

    private function item(string $slug, ItemType $type, int $power): Item
    {
        return new Item(['slug' => $slug, 'name' => ucfirst($slug), 'type' => $type, 'power' => $power]);
    }

    private function tribute(?District $district = null, string $name = 'Dean'): Tribute
    {
        return new Tribute($name, Gender::Male, $district ?? $this->district());
    }

    // ---- Stats iniciales ----

    public function test_starts_with_full_stats_and_district_bonuses(): void
    {
        $tribute = $this->tribute($this->district(strength: 3, energy: -5)); // Norte Helado

        $this->assertSame(20, $tribute->hp());
        $this->assertSame(15, $tribute->energy());
        $this->assertSame(8, $tribute->strength());
        $this->assertTrue($tribute->isAlive());
    }

    // ---- Vida ----

    public function test_damage_reduces_hp(): void
    {
        $tribute = $this->tribute();

        $applied = $tribute->changeHp(-6);

        $this->assertSame(14, $tribute->hp());
        $this->assertSame(-6, $applied);
    }

    public function test_healing_cannot_exceed_max_hp(): void
    {
        $tribute = $this->tribute();
        $tribute->changeHp(-3);

        $applied = $tribute->changeHp(10);

        $this->assertSame(20, $tribute->hp());
        $this->assertSame(3, $applied); // solo ha podido curar 3
    }

    public function test_dies_when_hp_reaches_zero(): void
    {
        $tribute = $this->tribute();

        $applied = $tribute->changeHp(-25);

        $this->assertSame(0, $tribute->hp());
        $this->assertSame(-20, $applied);
        $this->assertFalse($tribute->isAlive());
    }

    public function test_dead_tribute_cannot_be_healed(): void
    {
        $tribute = $this->tribute();
        $tribute->changeHp(-20);

        $tribute->changeHp(10);

        $this->assertSame(0, $tribute->hp());
    }

    public function test_exhausted_tribute_takes_double_damage(): void
    {
        $tribute = $this->tribute();
        $tribute->changeEnergy(-100);

        $tribute->changeHp(-4);

        $this->assertTrue($tribute->isExhausted());
        $this->assertSame(12, $tribute->hp());
    }

    // ---- Energía ----

    public function test_energy_stays_between_zero_and_max(): void
    {
        $tribute = $this->tribute($this->district(energy: 10)); // Sur Quemado: 30 de energía

        $tribute->changeEnergy(-100);
        $this->assertSame(0, $tribute->energy());

        $tribute->changeEnergy(100);
        $this->assertSame(30, $tribute->energy());
    }

    // ---- Mochila ----

    public function test_best_item_of_type_picks_highest_power(): void
    {
        $tribute = $this->tribute();
        $tribute->addItem($this->item('taser', ItemType::Weapon, 3));
        $tribute->addItem($this->item('pistolaclavos', ItemType::Weapon, 8));
        $tribute->addItem($this->item('chaleco', ItemType::Armor, 5));

        $this->assertSame('pistolaclavos', $tribute->bestItemOfType(ItemType::Weapon)->slug);
        $this->assertSame('chaleco', $tribute->bestItemOfType(ItemType::Armor)->slug);
        $this->assertNull($tribute->bestItemOfType(ItemType::Heal));
    }

    public function test_remove_item_only_removes_one_unit(): void
    {
        $tribute = $this->tribute();
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $tribute->addItem($medkit);
        $tribute->addItem($medkit);

        $tribute->removeItem($medkit);
        $this->assertTrue($tribute->hasItem($medkit));
        $this->assertCount(1, $tribute->inventory());

        $tribute->removeItem($medkit);
        $this->assertFalse($tribute->hasItem($medkit));
    }

    // ---- Alianzas ----

    public function test_alliance_is_mutual(): void
    {
        $dean = $this->tribute(name: 'Dean');
        $avery = $this->tribute(name: 'Avery');

        $dean->befriend($avery);

        $this->assertTrue($dean->isAllyOf($avery));
        $this->assertTrue($avery->isAllyOf($dean));
    }

    public function test_befriending_twice_does_not_duplicate(): void
    {
        $dean = $this->tribute(name: 'Dean');
        $avery = $this->tribute(name: 'Avery');

        $dean->befriend($avery);
        $avery->befriend($dean);

        $this->assertCount(1, $dean->allies());
        $this->assertCount(1, $avery->allies());
    }

    public function test_joining_someone_joins_their_whole_alliance(): void
    {
        $dean = $this->tribute(name: 'Dean');
        $avery = $this->tribute(name: 'Avery');
        $noah = $this->tribute(name: 'Noah');
        $dean->befriend($avery);

        $noah->befriend($dean);

        $this->assertTrue($noah->isAllyOf($avery));   // Noah y Avery también son aliados
        $this->assertCount(3, $avery->alliance());
    }

    public function test_leaving_the_alliance_breaks_it_with_everyone(): void
    {
        $dean = $this->tribute(name: 'Dean');
        $avery = $this->tribute(name: 'Avery');
        $noah = $this->tribute(name: 'Noah');
        $dean->befriend($avery);
        $dean->befriend($noah);

        $dean->leaveAlliance();

        $this->assertFalse($dean->isAllyOf($avery));
        $this->assertFalse($noah->isAllyOf($dean));
        $this->assertTrue($avery->isAllyOf($noah));   // los demás siguen juntos
    }

    public function test_dead_tribute_leaves_the_alliance(): void
    {
        $dean = $this->tribute(name: 'Dean');
        $avery = $this->tribute(name: 'Avery');
        $dean->befriend($avery);

        $avery->changeHp(-20);

        $this->assertSame([], $dean->allies());
    }
}
