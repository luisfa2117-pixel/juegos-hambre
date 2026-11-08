<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Actions\ResumeGame;
use App\Enums\EventCategory;
use App\Enums\Terrain;
use App\Models\ArenaEvent;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Database\Seeders\GameEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArenasTest extends TestCase
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

    private function play(Terrain $terrain, int $seed = 42): Game
    {
        return app(PlayGame::class)->handle($this->user, Character::all(), $seed, terrain: $terrain);
    }

    /** Todos los textos que han salido en la partida */
    private function texts(Game $game): array
    {
        return $game->rounds->flatMap(fn ($round) => array_column($round->events, 'text'))->all();
    }

    public function test_the_seeder_loads_the_themed_events_and_tags_the_city_ones(): void
    {
        $desert = GameEvent::where('category', '!=', EventCategory::Arena)->get()
            ->filter(fn (GameEvent $event) => $event->terrains === ['desert']);
        $this->assertGreaterThan(20, $desert->count());

        $metro = GameEvent::where('text', '{A} durmió de un tirón en un vagón de metro abandonado')->sole();
        $this->assertSame(['city'], $metro->terrains);

        $this->assertSame(['city'], ArenaEvent::where('slug', 'inundacion')->sole()->terrains);
        $this->assertSame(['peaks'], ArenaEvent::where('slug', 'avalancha')->sole()->terrains);
    }

    public function test_some_catastrophes_can_happen_in_every_arena(): void
    {
        $this->assertNull(ArenaEvent::where('slug', 'banquete')->sole()->terrains);
        $this->assertNull(ArenaEvent::where('slug', 'campo_fuerza')->sole()->terrains);
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $events = GameEvent::count();
        $arenas = ArenaEvent::count();

        $this->seed(GameEventSeeder::class);

        $this->assertSame($events, GameEvent::count());
        $this->assertSame($arenas, ArenaEvent::count());
    }

    public function test_a_desert_game_starts_with_its_announcement_and_never_shows_city_events(): void
    {
        $game = $this->play(Terrain::Desert);

        $this->assertSame(Terrain::Desert, $game->terrain);
        $this->assertSame('El desierto de sal', $game->rounds->first()->events[0]['arena']);

        $cityOnly = GameEvent::all()->filter(fn (GameEvent $event) => $event->terrains === ['city'])->pluck('text')->all();
        $this->assertSame([], array_values(array_intersect($this->texts($game), $cityOnly)));
    }

    public function test_city_games_have_no_announcement(): void
    {
        $game = $this->play(Terrain::City);

        $this->assertSame(Terrain::City, $game->terrain);
        $this->assertNull($game->rounds->first()->events[0]['arena']);
    }

    public function test_a_resumed_game_stays_in_its_arena(): void
    {
        $game = $this->play(Terrain::Swamp);
        $before = $this->texts($game);

        $resumed = app(ResumeGame::class)->handle($game, 2);

        $this->assertSame($before, $this->texts($resumed));
    }

    public function test_the_form_lets_you_choose_the_arena(): void
    {
        $this->get(route('games.create'))
            ->assertOk()
            ->assertSee('La arena')
            ->assertSee(Terrain::Peaks->label());

        $this->post(route('games.store'), ['characters' => Character::pluck('id')->all(), 'terrain' => 'peaks'])
            ->assertRedirect();

        $this->assertSame(Terrain::Peaks, Game::sole()->terrain);
    }

    public function test_store_rejects_unknown_arenas(): void
    {
        $this->post(route('games.store'), ['characters' => Character::pluck('id')->all(), 'terrain' => 'luna'])
            ->assertSessionHasErrors('terrain');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_the_game_page_shows_the_arena_and_the_replay_keeps_it(): void
    {
        $game = $this->play(Terrain::Desert);

        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))
            ->assertOk()
            ->assertSee(Terrain::Desert->label())
            ->assertSee('name="terrain" value="desert"', escape: false);
    }

    public function test_the_editor_saves_the_arenas_of_an_event(): void
    {
        $data = [
            'category' => 'solo',
            'actor_hp' => -3, 'actor_energy' => 0, 'target_hp' => 0, 'target_energy' => 0,
        ];

        $this->post(route('events.store'), [...$data, 'text' => '{A} se perdió entre las dunas', 'terrains' => ['desert']])
            ->assertSessionHasNoErrors();
        $this->post(route('events.store'), [...$data, 'text' => '{A} miró las estrellas', 'terrains' => ['city', 'desert', 'peaks', 'swamp']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['desert'], GameEvent::where('text', '{A} se perdió entre las dunas')->sole()->terrains);
        // Todas marcadas = vale para todas
        $this->assertNull(GameEvent::where('text', '{A} miró las estrellas')->sole()->terrains);
    }

    public function test_export_keeps_the_arenas(): void
    {
        $path = storage_path('framework/testing/eventos-arenas.json');
        File::ensureDirectoryExists(dirname($path));

        $this->artisan('events:export', ['--path' => $path])->assertSuccessful();
        $json = json_decode(File::get($path), true);

        $metro = collect($json['eventosIndividuales'])->firstWhere('descripcion', '{A} durmió de un tirón en un vagón de metro abandonado');
        $this->assertSame(['ciudad'], $metro['escenarios']);
        $this->assertSame(['cumbres'], collect($json['catastrofes'])->firstWhere('id', 'avalancha')['escenarios']);

        File::delete($path);
    }
}
