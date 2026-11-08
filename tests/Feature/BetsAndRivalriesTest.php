<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Actions\ResumeGame;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameKill;
use App\Models\GameTribute;
use App\Models\User;
use App\Services\Rivalries;
use App\Simulation\GameRules;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\TestCase;

class BetsAndRivalriesTest extends TestCase
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

    private function play(int $seed = 42, ?int $bet = null): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed, new GameRules, false, $bet);
    }

    private function store(array $data): TestResponse
    {
        return $this->post(route('games.store'), ['characters' => Character::pluck('id')->all(), ...$data]);
    }

    /**
     * Una semilla en la que alguien gana (si mueren todos a la vez, no hay ganador por el que apostar).
     * Juega esa partida una vez y la borra: solo queremos saber quién gana.
     *
     * @return array{int, GameTribute}
     */
    private function seedWithWinner(): array
    {
        foreach (range(1, 20) as $seed) {
            $game = $this->play($seed);
            $winner = $game->winner();
            $game->delete();

            if ($winner !== null) {
                return [$seed, $winner];
            }
        }

        $this->fail('Ninguna de las 20 semillas tiene ganador');
    }

    /** Hace como si hubieras visto la partida hasta el final */
    private function watch(Game $game): void
    {
        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))->assertOk();
    }

    // ---- Apuestas ----

    public function test_the_bet_is_saved_on_the_chosen_tribute(): void
    {
        $character = Character::orderBy('id')->first();

        $this->store(['bet' => $character->id])->assertRedirect();

        $game = Game::sole();
        $this->assertSame($character->id, $game->bet()->character_id);
        $this->assertSame(1, $game->tributes()->where('is_bet', true)->count());
    }

    public function test_you_can_only_bet_on_someone_who_plays(): void
    {
        $ids = Character::orderBy('id')->pluck('id');

        $this->post(route('games.store'), ['characters' => $ids->take(4)->all(), 'bet' => $ids->last()])
            ->assertSessionHasErrors('bet');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_you_cannot_bet_with_a_seed(): void
    {
        $this->store(['seed' => 42, 'bet' => Character::value('id')])->assertSessionHasErrors('bet');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_play_game_rejects_a_bet_on_someone_who_does_not_play(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PlayGame::class)->handle($this->user, Character::orderBy('id')->take(3)->get(), 1, betCharacterId: Character::max('id'));
    }

    public function test_the_game_page_shows_your_bet_and_whether_you_won(): void
    {
        [$seed, $winner] = $this->seedWithWinner();

        // Apostamos por el ganador y por otro, con la misma partida
        $right = $this->play($seed, $winner->character_id);
        $loser = $right->tributes->firstWhere('placement', '>', 1);
        $wrong = $this->play($seed, $loser->character_id);

        $this->get(route('games.show', [$right, 'round' => 1]))->assertSee('Tu apuesta')->assertDontSee('¡Acertaste!');
        $this->get(route('games.show', [$right, 'round' => $right->total_rounds]))->assertSee('¡Acertaste!');
        $this->get(route('games.show', [$wrong, 'round' => $wrong->total_rounds]))->assertSee('Fallaste.');
    }

    public function test_stats_only_count_bets_on_games_you_have_watched(): void
    {
        [$seed, $winner] = $this->seedWithWinner();

        $watched = $this->play($seed, $winner->character_id);
        $this->play($seed, $winner->character_id);   // esta no la ves
        $this->watch($watched);

        $this->get(route('stats.index'))
            ->assertOk()
            ->assertSee('100 %')
            ->assertSee('1 acertada de 1')
            ->assertSee('1 partida sin terminar de ver');
    }

    // ---- Asesinatos y némesis ----

    public function test_every_kill_is_recorded(): void
    {
        $game = $this->play();

        // Tantas filas como asesinatos se apuntan los tributos
        $this->assertSame((int) $game->tributes->sum('kills'), $game->kills()->count());

        foreach ($game->kills as $kill) {
            $this->assertSame($game->id, $kill->killer->game_id);
            $this->assertNotNull($kill->victim->died_in_round);
        }
    }

    public function test_kills_are_recalculated_when_a_game_is_replayed_from_a_round(): void
    {
        $game = $this->play();
        $before = $game->kills()->orderBy('id')->get(['killer_id', 'victim_id', 'round'])->toArray();

        app(ResumeGame::class)->handle($game, 2);

        $this->assertSame($before, $game->kills()->orderBy('id')->get(['killer_id', 'victim_id', 'round'])->toArray());
    }

    public function test_the_summarize_command_fills_in_old_games(): void
    {
        $game = $this->play();
        $count = $game->kills()->count();
        GameKill::query()->delete();

        $this->artisan('games:summarize')->assertSuccessful();

        $this->assertSame($count, $game->kills()->count());
    }

    public function test_the_nemesis_is_who_killed_the_character_most(): void
    {
        foreach (range(1, 8) as $seed) {
            $this->play($seed);
        }

        // Lo calculamos a mano, en PHP, para comparar con la consulta
        $counts = [];
        foreach (GameKill::with(['killer', 'victim'])->get() as $kill) {
            $counts[$kill->victim->character_id][$kill->killer->character_id][] = $kill->id;
        }

        $rivalries = app(Rivalries::class);
        $checked = 0;

        foreach ($counts as $victimId => $killers) {
            $most = max(array_map('count', $killers));
            $victim = Character::find($victimId);
            $nemesis = $rivalries->nemesisOf($victim);

            $this->assertSame($most, $nemesis->times);
            $this->assertCount($most, $killers[$nemesis->killer->id]);
            $this->assertSame($victimId, $nemesis->victim->id);
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_the_character_page_shows_nemesis_and_favourite_victim(): void
    {
        foreach (range(1, 8) as $seed) {
            $this->play($seed);
        }

        $victim = GameTribute::whereNotNull('killed_by')->firstOrFail()->character;
        $nemesis = app(Rivalries::class)->nemesisOf($victim);

        $this->get(route('characters.show', $victim))
            ->assertOk()
            ->assertSee('Su némesis')
            ->assertSee($nemesis->killer->name);

        $this->get(route('characters.show', $nemesis->killer))->assertSee('Su víctima favorita');
    }

    public function test_deleted_characters_do_not_count_as_rivals(): void
    {
        $this->play();
        $kill = GameKill::with('killer.character', 'victim.character')->firstOrFail();
        $victim = $kill->victim->character;

        // Borramos a todos los que le han matado alguna vez
        $killerIds = GameKill::whereHas('victim', fn ($query) => $query->where('character_id', $victim->id))
            ->with('killer')->get()->pluck('killer.character_id')->unique();
        Character::whereIn('id', $killerIds)->delete();

        $this->assertNull(app(Rivalries::class)->nemesisOf($victim));
    }

    public function test_rivalries_need_at_least_two_kills(): void
    {
        $this->play();

        foreach (app(Rivalries::class)->topFor($this->user) as $rivalry) {
            $this->assertGreaterThanOrEqual(2, $rivalry->times);
        }

        $this->get(route('stats.index'))->assertOk()->assertSee('Rivalidades');
    }
}
