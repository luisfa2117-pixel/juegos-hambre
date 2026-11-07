<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Models\GameEvent;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GameEventControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Todas las páginas exigen sesión: entramos como el usuario de pruebas del seeder
        $this->user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
        $this->actingAs($this->user);
        $this->withoutVite();
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'category' => 'solo',
            'text' => '{A} tropezó con un cable de alta tensión',
            'actor_hp' => -5, 'actor_energy' => -2, 'target_hp' => 0, 'target_energy' => 0,
            'requires_item_id' => null, 'gives_item_id' => null,
        ], $overrides);
    }

    private function itemId(string $slug): int
    {
        return Item::where('slug', $slug)->value('id');
    }

    // ---- Lista ----

    public function test_lists_events_with_example_names_and_filters_by_category(): void
    {
        $this->get(route('events.index'))
            ->assertOk()
            ->assertSee('Todos (411)');

        $this->get(route('events.index', ['category' => 'weapon']))
            ->assertOk()
            ->assertSee('acribilló a')
            ->assertDontSee('pisó una mina');
    }

    // ---- Crear ----

    public function test_creates_an_event(): void
    {
        $this->post(route('events.store'), $this->data())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('events.index', ['category' => 'solo']));

        $this->assertDatabaseHas('game_events', [
            'text' => '{A} tropezó con un cable de alta tensión',
            'participants' => 1,
            'actor_hp' => -5,
        ]);
    }

    public function test_participants_come_from_the_category(): void
    {
        $this->post(route('events.store'), $this->data([
            'category' => 'trio',
            'text' => '{A}, {B} y {C} cayeron por un hueco del ascensor',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(3, GameEvent::where('text', 'like', '%hueco del ascensor%')->value('participants'));
    }

    public function test_solo_events_ignore_target_effects(): void
    {
        $this->post(route('events.store'), $this->data(['target_hp' => -10]));

        $this->assertSame(0, GameEvent::where('text', 'like', '%cable de alta%')->value('target_hp'));
    }

    public function test_saves_which_personality_the_event_is_for(): void
    {
        $this->post(route('events.store'), $this->data(['personality' => 'coward']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('game_events', ['text' => '{A} tropezó con un cable de alta tensión', 'personality' => 'coward']);

        $this->post(route('events.store'), $this->data(['text' => '{A} se rió', 'personality' => 'psicopata']))
            ->assertSessionHasErrors('personality');
    }

    public function test_saves_when_the_event_can_happen(): void
    {
        $this->post(route('events.store'), $this->data(['phase' => 'night']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('game_events', ['text' => '{A} tropezó con un cable de alta tensión', 'phase' => 'night']);

        $this->post(route('events.store'), $this->data(['text' => '{A} se perdió', 'phase' => 'mediodia']))
            ->assertSessionHasErrors('phase');
    }

    // ---- Validación del texto ----

    public function test_text_must_name_every_participant(): void
    {
        $this->post(route('events.store'), $this->data(['category' => 'duo', 'text' => '{A} disparó al aire']))
            ->assertSessionHasErrors('text');
    }

    public function test_text_cannot_use_participants_that_do_not_exist(): void
    {
        $this->post(route('events.store'), $this->data(['text' => '{A} vio a {B} a lo lejos']))
            ->assertSessionHasErrors('text');
    }

    public function test_gender_markers_must_be_well_formed(): void
    {
        $this->post(route('events.store'), $this->data(['text' => '{A} quedó herid{A:o}']))
            ->assertSessionHasErrors('text');

        $this->post(route('events.store'), $this->data(['text' => '{A} quedó herid{A:o/a']))
            ->assertSessionHasErrors('text');

        $this->post(route('events.store'), $this->data(['text' => '{A} quedó herid{A:o/a}']))
            ->assertSessionHasNoErrors();
    }

    public function test_text_must_be_unique_within_its_category(): void
    {
        $existing = GameEvent::where('category', EventCategory::Solo)->first();

        $this->post(route('events.store'), $this->data(['text' => $existing->text]))
            ->assertSessionHasErrors(['text' => 'Ya hay un evento de este tipo con ese mismo texto.']);

        // Editándolo, su propio texto no cuenta como repetido
        $this->put(route('events.update', $existing), $this->data(['text' => $existing->text]))
            ->assertSessionHasNoErrors();
    }

    // ---- Validación de objetos ----

    public function test_discoveries_must_give_an_item_and_others_cannot(): void
    {
        $this->post(route('events.store'), $this->data(['category' => 'discovery', 'text' => '{A} abrió una caja fuerte']))
            ->assertSessionHasErrors('gives_item_id');

        $this->post(route('events.store'), $this->data(['gives_item_id' => $this->itemId('botiquin')]))
            ->assertSessionHasErrors(['gives_item_id' => 'Solo los descubrimientos dan objetos.']);

        $this->post(route('events.store'), $this->data([
            'category' => 'discovery', 'text' => '{A} abrió una caja fuerte', 'gives_item_id' => $this->itemId('botiquin'),
        ]))->assertSessionHasNoErrors();
    }

    public function test_weapon_attacks_must_require_a_weapon(): void
    {
        $attack = ['category' => 'weapon', 'text' => '{A} golpeó a {B} con la culata', 'target_hp' => -8];

        $this->post(route('events.store'), $this->data($attack))
            ->assertSessionHasErrors('requires_item_id');

        $this->post(route('events.store'), $this->data([...$attack, 'requires_item_id' => $this->itemId('chaleco')]))
            ->assertSessionHasErrors(['requires_item_id' => 'Para un ataque con arma, el objeto tiene que ser un arma.']);

        $this->post(route('events.store'), $this->data([...$attack, 'requires_item_id' => $this->itemId('pistola')]))
            ->assertSessionHasNoErrors();
    }

    public function test_catastrophe_events_are_not_managed_from_the_editor(): void
    {
        $this->post(route('events.store'), $this->data(['category' => 'arena']))
            ->assertSessionHasErrors('category');

        $arenaEvent = GameEvent::where('category', EventCategory::Arena)->firstOrFail();
        $this->get(route('events.edit', $arenaEvent))->assertNotFound();
        $this->delete(route('events.destroy', $arenaEvent))->assertNotFound();
        $this->assertModelExists($arenaEvent);
    }

    public function test_stats_must_be_between_minus_20_and_20(): void
    {
        $this->post(route('events.store'), $this->data(['actor_hp' => -50]))
            ->assertSessionHasErrors('actor_hp');
    }

    // ---- Vista previa ----

    public function test_preview_returns_the_text_with_men_and_women(): void
    {
        $this->postJson(route('events.preview'), ['text' => '{A} y {B} quedaron atrapad{AB:os/as}'])
            ->assertOk()
            ->assertExactJson([
                'men' => 'Dean y Noah quedaron atrapados',
                'women' => 'Avery y Hannah quedaron atrapadas',
            ]);
    }

    // ---- Eliminar ----

    public function test_deletes_an_event(): void
    {
        $event = GameEvent::where('category', EventCategory::Duo)->first();

        $this->delete(route('events.destroy', $event))->assertRedirect();

        $this->assertModelMissing($event);
    }

    public function test_cannot_delete_the_last_one_person_event(): void
    {
        $soloEvents = GameEvent::whereIn('category', [EventCategory::Solo, EventCategory::Discovery])->get();
        $last = $soloEvents->pop();
        GameEvent::destroy($soloEvents->pluck('id'));

        $this->delete(route('events.destroy', $last))->assertSessionHas('error');

        $this->assertModelExists($last);
    }

    // ---- Exportar al JSON ----

    public function test_export_writes_a_json_the_seeder_can_read_back(): void
    {
        $this->post(route('events.store'), $this->data());
        $path = storage_path('framework/testing/eventos-test.json');
        File::ensureDirectoryExists(dirname($path));

        $this->artisan('events:export', ['--path' => $path])->assertSuccessful();

        $json = json_decode(File::get($path), true);
        $this->assertSame(
            GameEvent::where('category', '!=', EventCategory::Arena)->count(),
            collect($json)->except('catastrofes')->flatten(1)->count(),
        );
        $this->assertCount(8, $json['catastrofes']);
        $this->assertSame(
            GameEvent::where('category', EventCategory::Arena)->count(),
            collect($json['catastrofes'])->sum(fn (array $arena) => count($arena['eventos'])),
        );
        $this->assertContains('{A} tropezó con un cable de alta tensión', array_column($json['eventosIndividuales'], 'descripcion'));
        $this->assertSame('cobarde', collect($json['eventosIndividuales'])->firstWhere('descripcion', '{A} lloró en silencio dentro de un armario')['personalidad']);
        $this->assertSame('noche', collect($json['eventosIndividuales'])->firstWhere('descripcion', '{A} pasó la noche a la intemperie bajo una lluvia ácida')['fase']);
        $this->assertSame('PISTOLA', collect($json['eventosArma'])->firstWhere('descripcion', '{A} encañonó a {B} y le disparó a quemarropa')['requiere']);

        File::delete($path);
    }
}
