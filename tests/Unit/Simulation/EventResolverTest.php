<?php

namespace Tests\Unit\Simulation;

use App\Enums\EventCategory;
use App\Enums\Gender;
use App\Enums\ItemType;
use App\Models\District;
use App\Models\GameEvent;
use App\Models\Item;
use App\Simulation\EventResolver;
use App\Simulation\Tribute;
use InvalidArgumentException;
use Tests\TestCase;

class EventResolverTest extends TestCase
{
    private EventResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EventResolver();
    }

    // ---- Ayudantes para crear datos de prueba sin base de datos ----

    private function tribute(string $name, int $strengthBonus = 0, Gender $gender = Gender::Male): Tribute
    {
        $district = new District([
            'slug' => 'test', 'name' => 'Distrito de prueba',
            'strength_bonus' => $strengthBonus, 'energy_bonus' => 0,
        ]);

        return new Tribute($name, $gender, $district);
    }

    private function item(string $slug, ItemType $type, int $power): Item
    {
        return new Item(['slug' => $slug, 'name' => ucfirst($slug), 'type' => $type, 'power' => $power]);
    }

    /**
     * Crea un evento en memoria. Con setRelation le "enchufamos" los objetos
     * sin que Eloquent tenga que buscarlos en la base de datos.
     */
    private function event(
        EventCategory $category,
        string $text,
        array $stats = [],
        ?Item $requires = null,
        ?Item $gives = null,
    ): GameEvent {
        $event = new GameEvent(array_merge([
            'category' => $category,
            'text' => $text,
            'participants' => substr_count($text, '{'),
            'actor_hp' => 0, 'actor_energy' => 0,
            'target_hp' => 0, 'target_energy' => 0,
        ], $stats));

        $event->setRelation('requiresItem', $requires);
        $event->setRelation('givesItem', $gives);

        return $event;
    }

    // ---- Texto ----

    public function test_replaces_labels_with_names(): void
    {
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B} desde un tejado');

        $result = $this->resolver->apply($event, [$this->tribute('Dean'), $this->tribute('Avery')]);

        $this->assertSame('Dean disparó a Avery desde un tejado', $result->text);
    }

    public function test_uses_gender_markers(): void
    {
        $event = $this->event(EventCategory::Ally, '{A} y {B} asaltaron junt{AB:os/as} un puesto', ['participants' => 2]);
        $avery = $this->tribute('Avery', gender: Gender::Female);
        $avril = $this->tribute('Avril', gender: Gender::Female);

        $result = $this->resolver->apply($event, [$avery, $avril]);

        $this->assertSame('Avery y Avril asaltaron juntas un puesto', $result->text);
    }

    // ---- Vida y energía ----

    public function test_applies_hp_and_energy_to_actor_and_targets(): void
    {
        $dean = $this->tribute('Dean');
        $avery = $this->tribute('Avery');
        $event = $this->event(EventCategory::Duo, '{A} y {B} se pelearon', [
            'actor_hp' => -4, 'actor_energy' => -2, 'target_hp' => -6, 'target_energy' => -1,
        ]);

        $this->resolver->apply($event, [$dean, $avery]);

        $this->assertSame(16, $dean->hp());
        $this->assertSame(18, $dean->energy());
        $this->assertSame(14, $avery->hp());
        $this->assertSame(19, $avery->energy());
    }

    public function test_all_targets_receive_the_same_change(): void
    {
        [$a, $b, $c] = [$this->tribute('A'), $this->tribute('B'), $this->tribute('C')];
        $event = $this->event(EventCategory::Trio, '{A} lanzó una bomba hacia {B} y {C}', ['target_hp' => -7]);

        $this->resolver->apply($event, [$a, $b, $c]);

        $this->assertSame(20, $a->hp());
        $this->assertSame(13, $b->hp());
        $this->assertSame(13, $c->hp());
    }

    // ---- Armas y fuerza ----

    public function test_weapon_damage_includes_strength_bonus_and_weapon_is_used_up(): void
    {
        $knife = $this->item('cuchillo', ItemType::Weapon, 5);
        $dean = $this->tribute('Dean', strengthBonus: 3);   // fuerza 8 => +3 de daño
        $dean->addItem($knife);
        $avery = $this->tribute('Avery');
        $event = $this->event(EventCategory::Weapon, '{A} apuñaló a {B}', ['target_hp' => -10], requires: $knife);

        $this->resolver->apply($event, [$dean, $avery]);

        $this->assertSame(7, $avery->hp());   // 20 - (10 + 3)
        $this->assertFalse($dean->hasItem($knife));
    }

    public function test_strength_does_not_affect_non_weapon_events(): void
    {
        $dean = $this->tribute('Dean', strengthBonus: 3);
        $avery = $this->tribute('Avery');
        $event = $this->event(EventCategory::Duo, '{A} golpeó a {B}', ['target_hp' => -6]);

        $this->resolver->apply($event, [$dean, $avery]);

        $this->assertSame(14, $avery->hp());
    }

    // ---- Defensa ----

    public function test_armor_absorbs_damage_in_duels_and_breaks(): void
    {
        $vest = $this->item('chaleco', ItemType::Armor, 5);
        $avery = $this->tribute('Avery');
        $avery->addItem($vest);
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B}', ['target_hp' => -8]);

        $result = $this->resolver->apply($event, [$this->tribute('Dean'), $avery]);

        $this->assertSame(17, $avery->hp());   // 20 - (8 - 5)
        $this->assertFalse($avery->hasItem($vest));
        $this->assertStringContainsString('absorbió 5', $result->notes[0]);
    }

    public function test_armor_never_heals(): void
    {
        $shield = $this->item('escudo', ItemType::Armor, 8);
        $avery = $this->tribute('Avery');
        $avery->addItem($shield);
        $event = $this->event(EventCategory::Duo, '{A} empujó a {B}', ['target_hp' => -3]);

        $this->resolver->apply($event, [$this->tribute('Dean'), $avery]);

        $this->assertSame(20, $avery->hp());
    }

    public function test_armor_does_not_protect_in_group_events(): void
    {
        $vest = $this->item('chaleco', ItemType::Armor, 5);
        [$a, $b, $c] = [$this->tribute('A'), $this->tribute('B'), $this->tribute('C')];
        $b->addItem($vest);
        $event = $this->event(EventCategory::Trio, '{A}, {B} y {C} quedaron atrapados en un incendio', ['target_hp' => -5]);

        $this->resolver->apply($event, [$a, $b, $c]);

        $this->assertSame(15, $b->hp());
        $this->assertTrue($b->hasItem($vest));
    }

    // ---- Objetos ----

    public function test_discovery_puts_item_in_actor_inventory(): void
    {
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $dean = $this->tribute('Dean');
        $event = $this->event(EventCategory::Discovery, '{A} encontró una caja de suministros', gives: $medkit);

        $result = $this->resolver->apply($event, [$dean]);

        $this->assertTrue($dean->hasItem($medkit));
        $this->assertStringContainsString('Botiquin', $result->notes[0]);
    }

    public function test_required_item_is_consumed(): void
    {
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $dean = $this->tribute('Dean');
        $dean->addItem($medkit);
        $avery = $this->tribute('Avery');
        $avery->changeHp(-12);
        $event = $this->event(EventCategory::Ally, '{A} usó su botiquín para curar a {B}', ['target_hp' => 10], requires: $medkit);

        $this->resolver->apply($event, [$dean, $avery]);

        $this->assertSame(18, $avery->hp());
        $this->assertFalse($dean->hasItem($medkit));
    }

    // ---- Consumibles automáticos ----

    public function test_wounded_tribute_uses_medkit_automatically(): void
    {
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $avery = $this->tribute('Avery');
        $avery->addItem($medkit);
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B}', ['target_hp' => -12]);

        $result = $this->resolver->apply($event, [$this->tribute('Dean'), $avery]);

        $this->assertSame(18, $avery->hp());   // 20 - 12 = 8 → usa el botiquín → 18
        $this->assertFalse($avery->hasItem($medkit));
        $this->assertStringContainsString('recuperó 10 de vida', $result->notes[0]);
    }

    public function test_tribute_with_enough_hp_keeps_medkit(): void
    {
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $avery = $this->tribute('Avery');
        $avery->addItem($medkit);
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B}', ['target_hp' => -5]);

        $this->resolver->apply($event, [$this->tribute('Dean'), $avery]);

        $this->assertSame(15, $avery->hp());
        $this->assertTrue($avery->hasItem($medkit));
    }

    public function test_exhausted_tribute_uses_energy_drink(): void
    {
        $drink = $this->item('bebida_energetica', ItemType::Energy, 5);
        $dean = $this->tribute('Dean');
        $dean->addItem($drink);
        $event = $this->event(EventCategory::Solo, '{A} corrió durante horas', ['actor_energy' => -18]);

        $this->resolver->apply($event, [$dean]);

        $this->assertSame(7, $dean->energy());   // 20 - 18 = 2 → usa la bebida → 7
        $this->assertFalse($dean->hasItem($drink));
    }

    // ---- Muertes ----

    public function test_reports_deaths_and_dead_tributes_do_not_use_items(): void
    {
        $medkit = $this->item('botiquin', ItemType::Heal, 10);
        $avery = $this->tribute('Avery');
        $avery->addItem($medkit);
        $avery->changeHp(-15);
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B}', ['target_hp' => -12]);

        $result = $this->resolver->apply($event, [$this->tribute('Dean'), $avery]);

        $this->assertFalse($avery->isAlive());
        $this->assertSame(['Avery'], $result->deaths);
        $this->assertTrue($avery->hasItem($medkit));
    }

    // ---- Alianzas ----

    public function test_befriend_creates_alliance_and_betrayal_breaks_it(): void
    {
        $dean = $this->tribute('Dean');
        $avery = $this->tribute('Avery');

        $result = $this->resolver->apply($this->event(EventCategory::Befriend, '{A} y {B} acordaron una tregua'), [$dean, $avery]);
        $this->assertTrue($dean->isAllyOf($avery));
        $this->assertSame('🤝 Dean y Avery ahora son aliados.', $result->notes[0]);

        $this->resolver->apply($this->event(EventCategory::Betrayal, '{A} traicionó a {B}', ['target_hp' => -5]), [$dean, $avery]);
        $this->assertFalse($dean->isAllyOf($avery));
    }

    public function test_traitor_leaves_the_whole_alliance(): void
    {
        $dean = $this->tribute('Dean');
        $avery = $this->tribute('Avery');
        $noah = $this->tribute('Noah');
        $dean->befriend($avery);
        $dean->befriend($noah);

        $this->resolver->apply($this->event(EventCategory::Betrayal, '{A} traicionó a {B}', ['target_hp' => -5]), [$dean, $avery]);

        $this->assertFalse($dean->isAllyOf($noah));
        $this->assertTrue($avery->isAllyOf($noah));
    }

    // ---- Errores ----

    public function test_fails_if_actor_lacks_required_item(): void
    {
        $knife = $this->item('cuchillo', ItemType::Weapon, 5);
        $event = $this->event(EventCategory::Weapon, '{A} apuñaló a {B}', ['target_hp' => -10], requires: $knife);

        $this->expectException(InvalidArgumentException::class);

        $this->resolver->apply($event, [$this->tribute('Dean'), $this->tribute('Avery')]);
    }

    public function test_fails_with_wrong_number_of_participants(): void
    {
        $event = $this->event(EventCategory::Duo, '{A} disparó a {B}');

        $this->expectException(InvalidArgumentException::class);

        $this->resolver->apply($event, [$this->tribute('Dean')]);
    }
}
