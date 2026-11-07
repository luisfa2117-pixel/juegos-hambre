<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\District;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_name_must_be_unique(): void
    {
        $this->post(route('characters.store'), $this->validData(['name' => 'Dean']))
            ->assertSessionHasErrors(['name' => 'Ya hay un personaje con ese nombre.']);
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

        $game = app(\App\Actions\PlayGame::class)->handle(Character::all(), 42);

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
