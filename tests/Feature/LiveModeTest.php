<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveModeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
        $this->actingAs($user);
        $this->withoutVite();

        $this->game = app(PlayGame::class)->handle($user, Character::all(), 42);
    }

    public function test_a_round_you_have_not_seen_is_told_live(): void
    {
        $this->get(route('games.show', [$this->game, 'round' => 1]))
            ->assertOk()
            ->assertSee('data-live-round="', escape: false)
            ->assertSee("classList.add('live-pending')", escape: false);
    }

    public function test_reloading_a_round_does_not_tell_it_live_again(): void
    {
        $this->get(route('games.show', [$this->game, 'round' => 2]));

        $this->get(route('games.show', [$this->game, 'round' => 2]))
            ->assertOk()
            ->assertDontSee('data-live-round="', escape: false);

        // Ni una ronda anterior
        $this->get(route('games.show', [$this->game, 'round' => 1]))->assertDontSee('data-live-round="', escape: false);
    }

    public function test_only_the_last_round_is_told_live(): void
    {
        $response = $this->get(route('games.show', [$this->game, 'round' => 3]));

        $this->assertSame(1, substr_count($response->getContent(), 'data-live-round="'));
    }

    public function test_the_controls_are_always_there(): void
    {
        $this->get(route('games.show', [$this->game, 'round' => 1]));

        $this->get(route('games.show', [$this->game, 'round' => 1]))
            ->assertSee('data-live-toggle', escape: false)
            ->assertSee('data-live-sound', escape: false);
    }
}
