<?php

namespace Tests\Feature;

use App\Actions\CreateStarterCharacters;
use App\Actions\PlayGame;
use App\Models\GameEvent;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Que nadie pueda ver ni tocar lo que no es suyo */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $demo;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->demo = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();

        // Otro usuario normal (no administrador), con sus propios 8 personajes
        $this->other = User::factory()->create();
        app(CreateStarterCharacters::class)->handle($this->other);
    }

    public function test_each_user_only_sees_their_own_characters(): void
    {
        $this->actingAs($this->other);
        $mine = $this->other->characters()->first();
        $mine->update(['name' => 'Solo mío']);

        $this->get(route('characters.index'))->assertSee('Solo mío');

        $this->actingAs($this->demo)->get(route('characters.index'))->assertDontSee('Solo mío');
    }

    public function test_cannot_view_edit_or_delete_someone_elses_character(): void
    {
        $theirs = $this->demo->characters()->first();
        $this->actingAs($this->other);

        $this->get(route('characters.show', $theirs))->assertForbidden();
        $this->get(route('characters.edit', $theirs))->assertForbidden();
        $this->put(route('characters.update', $theirs), ['name' => 'Robado', 'gender' => 'male', 'district_id' => $theirs->district_id])
            ->assertForbidden();
        $this->delete(route('characters.destroy', $theirs))->assertForbidden();

        $this->assertModelExists($theirs);
        $this->assertNotSame('Robado', $theirs->fresh()->name);
    }

    public function test_two_users_can_have_characters_with_the_same_name(): void
    {
        // Los dos tienen ya un "Dean" (los 8 iniciales), y no pasa nada
        $this->assertSame(1, $this->demo->characters()->where('name', 'Dean')->count());
        $this->assertSame(1, $this->other->characters()->where('name', 'Dean')->count());
    }

    public function test_cannot_view_or_delete_someone_elses_game(): void
    {
        $game = app(PlayGame::class)->handle($this->demo, $this->demo->characters()->get(), 42);
        $this->actingAs($this->other);

        $this->get(route('games.show', $game))->assertForbidden();
        $this->delete(route('games.destroy', $game))->assertForbidden();
        $this->get(route('games.index'))->assertDontSee("Partida #{$game->id}");

        $this->assertModelExists($game);
    }

    public function test_cannot_play_with_someone_elses_characters(): void
    {
        $this->actingAs($this->other)
            ->post(route('games.store'), ['characters' => $this->demo->characters()->pluck('id')->all()])
            ->assertSessionHasErrors('characters.0');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_stats_only_count_your_own_games(): void
    {
        app(PlayGame::class)->handle($this->demo, $this->demo->characters()->get(), 42);

        $this->actingAs($this->other)->get(route('stats.index'))->assertSee('Aún no hay estadísticas');
        $this->actingAs($this->demo)->get(route('stats.index'))->assertSee('1 partida jugada');
    }

    public function test_only_admins_can_manage_events(): void
    {
        $event = GameEvent::first();
        $this->actingAs($this->other);

        $this->get(route('events.index'))->assertForbidden();
        $this->get(route('events.edit', $event))->assertForbidden();
        $this->delete(route('events.destroy', $event))->assertForbidden();
        $this->get(route('games.index'))->assertDontSee(route('events.index'));

        $this->actingAs($this->demo)->get(route('events.index'))->assertOk();
    }
}
