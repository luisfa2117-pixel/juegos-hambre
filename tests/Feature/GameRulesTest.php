<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Actions\ResumeGame;
use App\Enums\GameRule;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use App\Simulation\GameRules;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GameRulesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
        $this->actingAs($this->user);
        $this->withoutVite();
    }

    /** @param GameRule[] $rules */
    private function play(array $rules = [], bool $quell = false, int $seed = 42): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed, new GameRules($rules), $quell);
    }

    private function storeWith(array $data): TestResponse
    {
        return $this->post(route('games.store'), [
            'characters' => Character::pluck('id')->all(),
            'seed' => 42,
            ...$data,
        ]);
    }

    public function test_a_game_without_rules_is_the_same_as_before(): void
    {
        $game = $this->play();

        $this->assertSame([], $game->rules);
        $this->assertFalse($game->quell);
        // Sin reglas no hay aviso al principio
        $this->assertNull($game->rounds->first()->events[0]['arena']);
    }

    public function test_the_rules_are_saved_and_announced_in_the_first_round(): void
    {
        $game = $this->play([GameRule::HalfLife, GameRule::Armed]);

        $this->assertSame(['armed', 'half_life'], $game->rules);
        $this->assertSame('Reglas especiales', $game->rounds->first()->events[0]['arena']);
    }

    public function test_armed_tributes_start_with_a_weapon(): void
    {
        $game = $this->play([GameRule::Armed]);

        // En el registro de la primera ronda, todos llevan algo en la mochila
        foreach ($game->rounds->first()->tributes as $tribute) {
            $this->assertNotEmpty($tribute['items'], "{$tribute['name']} no lleva nada");
        }
    }

    public function test_the_quell_picks_its_rules_from_the_seed(): void
    {
        $game = $this->play(quell: true, seed: 7);

        $this->assertTrue($game->quell);
        $this->assertSame((new GameRules(GameRule::quell(7)))->values(), $game->rules);
        $this->assertSame('Vasallaje de los 25', $game->rounds->first()->events[0]['arena']);
    }

    public function test_a_resumed_game_keeps_its_rules(): void
    {
        $game = $this->play([GameRule::NoAlliances, GameRule::Catastrophes]);
        $before = $game->rounds->pluck('events')->all();

        $resumed = app(ResumeGame::class)->handle($game, 2);

        $this->assertSame($before, $resumed->rounds->pluck('events')->all());
    }

    public function test_the_form_shows_the_rules(): void
    {
        $this->get(route('games.create'))
            ->assertOk()
            ->assertSee('Vasallaje de los 25')
            ->assertSee(GameRule::Scarcity->label());
    }

    public function test_store_saves_the_chosen_rules(): void
    {
        $this->storeWith(['rules' => ['scarcity', 'armed']])->assertRedirect();

        $this->assertSame(['armed', 'scarcity'], Game::sole()->rules);
    }

    public function test_store_with_the_quell_ignores_the_other_rules(): void
    {
        $this->storeWith(['quell' => '1', 'rules' => ['armed']])->assertRedirect();

        $game = Game::sole();
        $this->assertTrue($game->quell);
        $this->assertSame((new GameRules(GameRule::quell(42)))->values(), $game->rules);
    }

    public function test_store_rejects_unknown_rules(): void
    {
        $this->storeWith(['rules' => ['todos_vuelan']])->assertSessionHasErrors('rules.0');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_store_rejects_rules_that_do_not_make_sense_together(): void
    {
        $this->storeWith(['rules' => ['no_alliances', 'traitors']])->assertSessionHasErrors('rules');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_the_game_page_shows_its_rules_and_the_replay_keeps_them(): void
    {
        $game = $this->play([GameRule::Scarcity]);

        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))
            ->assertOk()
            ->assertSee(GameRule::Scarcity->label())
            ->assertSee('name="rules[]" value="scarcity"', escape: false);
    }

    public function test_replaying_a_game_with_rules_gives_the_same_game(): void
    {
        $original = $this->play([GameRule::Armed, GameRule::Traitors]);

        $this->storeWith(['rules' => ['armed', 'traitors']]);

        $replay = Game::latest('id')->first();
        $this->assertSame(
            $original->rounds->pluck('events')->all(),
            $replay->rounds->pluck('events')->all(),
        );
    }
}
