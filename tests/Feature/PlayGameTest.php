<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Models\Character;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Tests\TestCase;

class PlayGameTest extends TestCase
{
    // Crea las tablas en la base de datos de pruebas y la deja limpia en cada test
    use RefreshDatabase;

    // Ejecuta el DatabaseSeeder antes de cada test: distritos, objetos, eventos y personajes
    protected $seed = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Todas las páginas exigen sesión: entramos como el usuario de pruebas del seeder
        $this->user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
        $this->actingAs($this->user);
    }

    private function play(?int $seed = 42): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed);
    }

    public function test_saves_the_game_with_its_tributes_and_rounds(): void
    {
        $game = $this->play();

        $this->assertDatabaseCount('games', 1);
        $this->assertCount(12, $game->tributes);
        $this->assertGreaterThan(0, $game->total_rounds);
        $this->assertCount($game->total_rounds, $game->rounds);

        $firstRound = $game->rounds->first();
        $this->assertSame(1, $firstRound->number);
        $this->assertNotEmpty($firstRound->events);
        $this->assertCount(12, $firstRound->tributes);
    }

    public function test_the_winner_survives_and_everyone_else_died(): void
    {
        foreach (range(1, 5) as $seed) {
            $game = $this->play($seed);
            $winner = $game->winner();

            foreach ($game->tributes as $tribute) {
                if ($winner !== null && $tribute->is($winner)) {
                    $this->assertNull($tribute->died_in_round);
                    $this->assertSame(1, $tribute->placement);
                } else {
                    $this->assertNotNull($tribute->died_in_round, "Semilla {$seed}: {$tribute->name}");
                    $this->assertLessThanOrEqual($game->total_rounds, $tribute->died_in_round);
                }
            }
        }
    }

    public function test_tributes_who_died_later_get_a_better_placement(): void
    {
        $game = $this->play();
        $dead = $game->tributes->whereNotNull('died_in_round');

        foreach ($dead as $a) {
            foreach ($dead as $b) {
                if ($a->died_in_round > $b->died_in_round) {
                    $this->assertLessThan($b->placement, $a->placement);
                }
            }
        }
    }

    public function test_last_round_snapshot_matches_the_final_result(): void
    {
        $game = $this->play();

        $aliveInSnapshot = collect($game->rounds->last()->tributes)->where('alive', true)->pluck('name')->sort()->values();
        $survivors = $game->tributes->whereNull('died_in_round')->pluck('name')->sort()->values();

        $this->assertSame($survivors->all(), $aliveInSnapshot->all());
    }

    public function test_same_seed_produces_the_same_game(): void
    {
        $first = $this->play(7);
        $second = $this->play(7);

        $this->assertSame(
            $first->rounds->pluck('events')->all(),
            $second->rounds->pluck('events')->all(),
        );
    }

    public function test_kills_are_counted_for_each_killer(): void
    {
        foreach (range(1, 5) as $seed) {
            $game = $this->play($seed);

            $kills = $game->rounds->flatMap(fn ($round) => collect($round->events)->pluck('kills')->flatten(1));
            $credited = $kills->sum(fn (array $kill) => count($kill['killers']));

            $this->assertSame($credited, (int) $game->tributes->sum('kills'), "Semilla {$seed}");

            foreach ($kills->where('killers', '!=', []) as $kill) {
                $victim = $game->tributes->firstWhere('name', $kill['victim']);
                $this->assertSame(implode(' y ', $kill['killers']), $victim->killed_by);
            }
        }
    }

    public function test_needs_at_least_two_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PlayGame::class)->handle($this->user, Character::limit(1)->get());
    }

    public function test_deleting_a_character_keeps_the_game_history(): void
    {
        $game = $this->play();

        Character::where('name', 'Dean')->delete();

        $dean = $game->tributes()->where('name', 'Dean')->first();
        $this->assertNull($dean->character_id);
        $this->assertSame('Dean', $dean->name);
    }

    public function test_deleting_a_game_deletes_its_rounds_and_tributes(): void
    {
        $game = $this->play();

        $game->delete();

        $this->assertDatabaseCount('game_rounds', 0);
        $this->assertDatabaseCount('game_tributes', 0);
    }
}
