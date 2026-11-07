<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
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

    private function playGame(int $seed = 42): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed);
    }

    public function test_home_redirects_to_games(): void
    {
        $this->get('/')->assertRedirect('/games');
    }

    public function test_index_shows_an_empty_state_and_then_the_games(): void
    {
        $this->get(route('games.index'))->assertOk()->assertSee('Todavía no has jugado ninguna partida.');

        $game = $this->playGame();

        $this->get(route('games.index'))->assertOk()->assertSee("Partida #{$game->id}");
    }

    public function test_create_form_lists_characters(): void
    {
        $this->get(route('games.create'))->assertOk()->assertSee('Dean')->assertSee('Empezar la partida');
    }

    public function test_store_plays_a_game_and_goes_to_round_one(): void
    {
        $response = $this->post(route('games.store'), [
            'characters' => Character::pluck('id')->all(),
            'seed' => 42,
        ]);

        $game = Game::firstOrFail();
        $response->assertRedirect(route('games.show', [$game, 'round' => 1]));
        $this->assertSame(42, $game->seed);
        $this->assertCount(12, $game->tributes);
    }

    public function test_store_needs_at_least_two_characters(): void
    {
        $this->post(route('games.store'), ['characters' => [Character::value('id')]])
            ->assertSessionHasErrors(['characters' => 'Elige al menos dos tributos.']);

        $this->assertDatabaseCount('games', 0);
    }

    public function test_same_characters_and_seed_give_the_same_game_in_any_order(): void
    {
        $ids = Character::pluck('id')->all();

        $this->post(route('games.store'), ['characters' => $ids, 'seed' => 7]);
        $this->post(route('games.store'), ['characters' => array_reverse($ids), 'seed' => 7]);

        [$first, $second] = Game::with('rounds')->orderBy('id')->get();
        $this->assertSame($first->rounds->pluck('events')->all(), $second->rounds->pluck('events')->all());
    }

    public function test_show_reveals_rounds_one_at_a_time(): void
    {
        $game = $this->playGame();
        $this->assertGreaterThan(1, $game->total_rounds);

        $this->get(route('games.show', [$game, 'round' => 1]))
            ->assertOk()
            ->assertSee('Baño de sangre')
            ->assertDontSee('Día 1')
            ->assertSee('Siguiente ronda')
            ->assertDontSee('Clasificación');
    }

    public function test_rounds_are_titled_by_phase(): void
    {
        $game = $this->playGame();
        $this->assertGreaterThanOrEqual(3, $game->total_rounds);

        $this->get(route('games.show', [$game, 'round' => 3]))
            ->assertOk()
            ->assertSeeInOrder(['Baño de sangre', 'Día 1', 'Noche 1'])
            ->assertSee('Tras la noche 1');
    }

    public function test_deaths_say_who_killed_them(): void
    {
        // Buscamos una partida en la que alguien mate a alguien
        foreach (range(1, 20) as $seed) {
            $game = $this->playGame($seed);
            $kill = $game->rounds->flatMap(fn ($round) => collect($round->events)->pluck('kills')->flatten(1))
                ->first(fn (array $kill) => $kill['killers'] !== []);

            if ($kill) {
                break;
            }
        }

        $this->assertNotNull($kill, 'Ninguna partida con asesinatos en 20 semillas');

        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))
            ->assertOk()
            ->assertSee("{$kill['victim']} ha muerto a manos de ".collect($kill['killers'])->join(', ', ' y '));
    }

    public function test_nights_show_the_fallen(): void
    {
        $game = $this->playGame();

        $this->get(route('games.show', [$game, 'round' => 3]))
            ->assertOk()
            ->assertSee('Los rostros en el cielo')
            ->assertSee('Los caídos desde el baño de sangre.');
    }

    public function test_last_round_shows_the_final_ranking(): void
    {
        $game = $this->playGame();

        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))
            ->assertOk()
            ->assertSee('Clasificación')
            ->assertDontSee('Siguiente ronda');
    }

    public function test_out_of_range_rounds_are_clamped(): void
    {
        $game = $this->playGame();

        $this->get(route('games.show', [$game, 'round' => 999]))->assertOk()->assertSee('Clasificación');
        $this->get(route('games.show', [$game, 'round' => -5]))->assertOk()->assertSee('Baño de sangre');
    }

    public function test_character_names_are_escaped(): void
    {
        Character::first()->update(['name' => '<script>alert(1)</script>']);
        $game = $this->playGame();

        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public function test_destroy_deletes_the_game(): void
    {
        $game = $this->playGame();

        $this->delete(route('games.destroy', $game))->assertRedirect(route('games.index'));

        $this->assertModelMissing($game);
    }
}
