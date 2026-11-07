<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Actions\ResumeGame;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use App\Simulation\Simulation;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ResumeGameTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
    }

    private function play(int $seed = 42): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed);
    }

    /** Lo que importa de una partida, para comparar dos versiones */
    private function fingerprint(Game $game): array
    {
        $game->load(['rounds', 'tributes']);

        return [
            'rounds' => $game->rounds->pluck('events', 'number')->all(),
            'results' => $game->tributes
                ->mapWithKeys(fn ($t) => [$t->name => [$t->died_in_round, $t->placement, $t->kills, $t->killed_by]])
                ->sortKeys()
                ->all(),
            'total_rounds' => $game->total_rounds,
        ];
    }

    public function test_every_round_saves_its_state(): void
    {
        $game = $this->play();

        foreach ($game->rounds as $round) {
            $this->assertNotNull($round->state, "Ronda {$round->number}");
            $this->assertSame($round->number, $round->state['round']);
            $this->assertCount(12, $round->state['tributes']);
        }
    }

    public function test_resuming_without_changes_gives_exactly_the_same_game(): void
    {
        foreach ([42, 7, 1234] as $seed) {
            $game = $this->play($seed);
            $before = $this->fingerprint($game);

            app(ResumeGame::class)->handle($game, 3);

            $this->assertSame($before, $this->fingerprint($game->fresh()), "Semilla {$seed}");
        }
    }

    public function test_rounds_already_played_are_not_touched(): void
    {
        $game = $this->play();
        $idsBefore = $game->rounds->pluck('id', 'number');

        app(ResumeGame::class)->handle($game, 3);

        $idsAfter = $game->fresh()->rounds->pluck('id', 'number');
        foreach ([1, 2, 3] as $number) {
            $this->assertSame($idsBefore[$number], $idsAfter[$number], "La ronda {$number} se ha vuelto a guardar");
        }
        $this->assertNotSame($idsBefore[4], $idsAfter[4], 'La ronda 4 debería haberse vuelto a jugar');
    }

    public function test_a_change_applies_from_the_next_round_on(): void
    {
        $game = $this->play();
        $roundTwo = $game->rounds->firstWhere('number', 2)->events;

        // Elegimos a alguien que siga vivo tras la ronda 2 y lo eliminamos antes de la 3
        $victim = collect($game->rounds->firstWhere('number', 2)->tributes)->firstWhere('alive', true)['name'];

        app(ResumeGame::class)->handle($game, 2, function (Simulation $simulation) use ($victim) {
            collect($simulation->tributes())->firstWhere('name', $victim)->changeHp(-100);
        });

        $game = $game->fresh(['rounds', 'tributes']);
        $this->assertSame($roundTwo, $game->rounds->firstWhere('number', 2)->events);
        $this->assertSame(3, $game->tributes->firstWhere('name', $victim)->died_in_round);
        $this->assertNotContains($victim, collect($game->rounds->firstWhere('number', 3)->events)->pluck('participants')->flatten());
    }

    public function test_cannot_resume_from_a_round_without_state(): void
    {
        $game = $this->play();
        $game->rounds()->where('number', 2)->update(['state' => null]);   // como una partida antigua

        $this->expectException(InvalidArgumentException::class);

        app(ResumeGame::class)->handle($game, 2);
    }

    public function test_cannot_resume_from_a_round_that_does_not_exist(): void
    {
        $game = $this->play();

        $this->expectException(InvalidArgumentException::class);

        app(ResumeGame::class)->handle($game, $game->total_rounds + 5);
    }
}
