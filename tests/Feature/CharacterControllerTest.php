<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
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

        // Los tests no necesitan el CSS/JS compilado por Vite
        $this->withoutVite();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kira',
            'gender' => 'female',
            'district_id' => District::where('slug', 'pacifico_tecno')->value('id'),
        ], $overrides);
    }

    public function test_lists_characters_grouped_by_district(): void
    {
        $this->get(route('characters.index'))
            ->assertOk()
            ->assertSee('Ruinas de la Costa Este')
            ->assertSee('Avery')
            ->assertSee('Horacio');
    }

    public function test_creates_a_character(): void
    {
        $this->post(route('characters.store'), $this->validData())
            ->assertRedirect(route('characters.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('characters', ['name' => 'Kira', 'gender' => 'female']);
    }

    public function test_saves_the_personality_and_it_is_optional(): void
    {
        $this->post(route('characters.store'), $this->validData(['personality' => 'loyal']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('characters', ['name' => 'Kira', 'personality' => 'loyal']);

        $this->post(route('characters.store'), $this->validData(['name' => 'Kai']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('characters', ['name' => 'Kai', 'personality' => null]);

        $this->post(route('characters.store'), $this->validData(['name' => 'Rex', 'personality' => 'psicopata']))
            ->assertSessionHasErrors('personality');
    }

    public function test_default_cast_has_a_boy_and_a_girl_in_each_of_the_6_districts(): void
    {
        $cast = $this->user->characters()->with('district')->get();

        $this->assertCount(12, $cast);
        foreach ($cast->groupBy('district.slug') as $district => $pair) {
            $this->assertCount(2, $pair, $district);
            $this->assertEqualsCanonicalizing(['female', 'male'], $pair->pluck('gender')->map->value->all(), $district);
        }
        $this->assertCount(6, $cast->groupBy('district_id'));
    }

    public function test_name_must_be_unique(): void
    {
        $this->post(route('characters.store'), $this->validData(['name' => 'Dean']))
            ->assertSessionHasErrors(['name' => 'Ya tienes un personaje con ese nombre.']);
    }

    public function test_rejects_invalid_gender_and_district(): void
    {
        $this->post(route('characters.store'), $this->validData(['gender' => 'robot', 'district_id' => 999]))
            ->assertSessionHasErrors(['gender', 'district_id']);

        $this->assertDatabaseMissing('characters', ['name' => 'Kira']);
    }

    public function test_updates_a_character_keeping_its_own_name(): void
    {
        $dean = Character::where('name', 'Dean')->first();

        $this->put(route('characters.update', $dean), $this->validData(['name' => 'Dean']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('characters.index'));

        $this->assertSame('pacifico_tecno', $dean->fresh()->district->slug);
    }

    public function test_deletes_a_character(): void
    {
        $dean = Character::where('name', 'Dean')->first();

        $this->delete(route('characters.destroy', $dean))
            ->assertRedirect(route('characters.index'));

        $this->assertModelMissing($dean);
    }

    public function test_character_page_shows_stats_and_games(): void
    {
        $dean = Character::where('name', 'Dean')->first();

        $this->get(route('characters.show', $dean))
            ->assertOk()
            ->assertSee('Todavía no ha jugado ninguna partida.');

        $game = app(\App\Actions\PlayGame::class)->handle($this->user, Character::all(), 42);

        $this->get(route('characters.show', $dean))
            ->assertOk()
            ->assertSee('Asesinatos')
            ->assertSee("Partida #{$game->id}");
    }

    public function test_create_and_edit_forms_load(): void
    {
        $this->get(route('characters.create'))->assertOk()->assertSee('Crear personaje');

        $dean = Character::where('name', 'Dean')->first();
        $this->get(route('characters.edit', $dean))->assertOk()->assertSee('Editar a Dean');
    }
}
