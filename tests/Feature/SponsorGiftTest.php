<?php

namespace Tests\Feature;

use App\Actions\CreateStarterCharacters;
use App\Actions\PlayGame;
use App\Actions\SendSponsorGift;
use App\Models\Character;
use App\Models\Game;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorGiftTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $user;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();
        $this->actingAs($this->user);
        $this->game = app(PlayGame::class)->handle($this->user, Character::all(), 42);
    }

    private function see(int $round): void
    {
        $this->get(route('games.show', [$this->game, 'round' => $round]))->assertOk();
        $this->game->refresh();
    }

    /** Alguien que siga vivo al final de esa ronda */
    private function aliveAfter(int $round): string
    {
        return collect($this->game->rounds()->where('number', $round)->first()->tributes)->firstWhere('alive', true)['name'];
    }

    private function gift(string $tribute, string $slug)
    {
        return $this->post(route('games.gifts.store', $this->game), [
            'tribute' => $tribute,
            'item_id' => Item::where('slug', $slug)->value('id'),
        ]);
    }

    public function test_after_sending_a_gift_the_page_goes_back_to_the_sponsor_box(): void
    {
        $this->see(3);

        $this->gift($this->aliveAfter(3), 'botiquin')
            ->assertRedirect(route('games.show', [$this->game, 'round' => 3]).'#patrocinar');

        $this->gift('Nadie', 'botiquin')   // también si hay un error
            ->assertRedirect(route('games.show', [$this->game, 'round' => 3]).'#patrocinar');

        $this->get(route('games.show', [$this->game, 'round' => 3]))->assertSee('id="patrocinar"', escape: false);
    }

    public function test_viewing_a_round_marks_it_as_seen(): void
    {
        $this->see(3);
        $this->assertSame(3, $this->game->revealed_round);

        $this->see(2);   // volver atrás no "olvida" lo visto
        $this->assertSame(3, $this->game->revealed_round);
    }

    public function test_the_form_only_appears_on_the_latest_round_seen(): void
    {
        $this->see(3);

        $this->get(route('games.show', [$this->game, 'round' => 3]))->assertSee('Patrocinar a un tributo');
        $this->get(route('games.show', [$this->game, 'round' => 2]))->assertDontSee('Patrocinar a un tributo');
    }

    public function test_the_gift_arrives_at_the_start_of_the_next_round(): void
    {
        $this->see(3);
        $avery = $this->aliveAfter(3);

        $this->gift($avery, 'cuchillo')->assertSessionHas('gift_status');

        $round4 = $this->game->fresh()->rounds()->where('number', 4)->first();
        $this->assertTrue($round4->events[0]['gift']);
        $this->assertSame("Un patrocinador ha enviado [Cuchillo de combate] a {$avery}.", $round4->events[0]['text']);

        // Lleva el cuchillo (con 4 usos, aunque lo haya usado en la ronda 4 le queda alguno)
        $items = collect($round4->state['tributes'])->firstWhere('name', $avery)['items'];
        $this->assertContains('cuchillo', array_column($items, 0));
    }

    public function test_rounds_already_seen_do_not_change(): void
    {
        $this->see(3);
        $before = $this->game->rounds()->where('number', '<=', 3)->pluck('events', 'number')->all();

        $this->gift($this->aliveAfter(3), 'nanobots');

        $this->assertSame($before, $this->game->rounds()->where('number', '<=', 3)->pluck('events', 'number')->all());
    }

    public function test_two_gifts_in_the_same_round_both_arrive(): void
    {
        $this->see(3);
        $alive = collect($this->game->rounds()->where('number', 3)->first()->tributes)->where('alive', true)->pluck('name')->values();

        $this->gift($alive[0], 'botiquin');
        $this->gift($alive[1], 'chaleco');

        $events = $this->game->fresh()->rounds()->where('number', 4)->first()->events;
        $this->assertSame("Un patrocinador ha enviado [Botiquín médico] a {$alive[0]}.", $events[0]['text']);
        $this->assertSame("Un patrocinador ha enviado [Chaleco antibalas] a {$alive[1]}.", $events[1]['text']);
    }

    public function test_there_are_only_three_gifts_per_game(): void
    {
        $this->see(3);
        $tribute = $this->aliveAfter(3);

        foreach (range(1, SendSponsorGift::MAX_GIFTS) as $i) {
            $this->gift($tribute, 'vendas')->assertSessionHas('gift_status');
        }

        $this->gift($tribute, 'vendas')->assertSessionHas('gift_error');
        $this->assertSame(SendSponsorGift::MAX_GIFTS, $this->game->gifts()->count());
    }

    public function test_no_gifts_once_you_have_seen_the_end(): void
    {
        $this->see($this->game->total_rounds);

        $this->gift($this->aliveAfter(1), 'botiquin')->assertSessionHas('gift_error');

        $this->assertSame(0, $this->game->gifts()->count());
    }

    public function test_only_living_tributes_can_be_sponsored(): void
    {
        $this->see(3);

        $this->gift('Nadie', 'botiquin')->assertSessionHas('gift_error');

        $dead = collect($this->game->rounds()->where('number', 3)->first()->tributes)->firstWhere('alive', false);
        if ($dead) {
            $this->gift($dead['name'], 'botiquin')->assertSessionHas('gift_error');
        }

        $this->assertSame(0, $this->game->gifts()->count());
    }

    public function test_only_items_from_the_catalog(): void
    {
        $this->see(3);

        $this->gift($this->aliveAfter(3), 'rifle_riel')->assertSessionHas('gift_error');

        $this->assertSame(0, $this->game->gifts()->count());
    }

    public function test_cannot_sponsor_someone_elses_game(): void
    {
        $other = User::factory()->create();
        app(CreateStarterCharacters::class)->handle($other);

        $this->actingAs($other)->gift($this->aliveAfter(1), 'botiquin')->assertForbidden();

        $this->assertSame(0, $this->game->gifts()->count());
    }

    public function test_results_and_stats_follow_the_new_ending(): void
    {
        $this->see(3);
        $this->gift($this->aliveAfter(3), 'nanobots');
        $game = $this->game->fresh(['rounds', 'tributes']);

        $this->assertSame($game->rounds->count(), $game->total_rounds);

        // Quien está muerto en la última ronda guardada tiene ronda de muerte, y quien no, no
        $last = collect($game->rounds->last()->tributes)->keyBy('name');
        foreach ($game->tributes as $tribute) {
            $this->assertSame(! $last[$tribute->name]['alive'], $tribute->died_in_round !== null, $tribute->name);
        }
    }
}
