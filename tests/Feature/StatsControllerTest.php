<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Models\Character;
use App\Models\GameTribute;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsControllerTest extends TestCase
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

    public function test_shows_an_empty_state_without_games(): void
    {
        $this->get(route('stats.index'))
            ->assertOk()
            ->assertSee('Aún no hay estadísticas');
    }

    public function test_shows_rankings_after_some_games(): void
    {
        foreach (range(1, 4) as $seed) {
            app(PlayGame::class)->handle($this->user, Character::all(), $seed);
        }

        $winner = GameTribute::where('placement', 1)->whereNull('died_in_round')->first();

        $this->get(route('stats.index'))
            ->assertOk()
            ->assertSee('4 partidas jugadas')
            ->assertSee('Más victorias')
            ->assertSee('Distritos')
            ->assertSee($winner->name);
    }

    public function test_character_stats_are_calculated_by_the_database(): void
    {
        foreach (range(1, 3) as $seed) {
            app(PlayGame::class)->handle($this->user, Character::all(), $seed);
        }

        $dean = Character::withStats()->where('name', 'Dean')->first();
        $appearances = GameTribute::where('name', 'Dean')->get();

        $this->assertSame(3, $dean->games_count);
        $this->assertSame($appearances->sum('kills'), (int) $dean->kills_total);
        $this->assertSame($appearances->whereNotNull('died_in_round')->count(), $dean->deaths_count);
    }
}
