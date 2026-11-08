<?php

namespace Tests\Feature;

use App\Actions\CreateGuestAccount;
use App\Actions\PlayGame;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestAccountTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function guest(): User
    {
        return app(CreateGuestAccount::class)->handle();
    }

    // ---- Portada y páginas públicas ----

    public function test_visitors_see_the_home_page_with_the_try_button(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Probar sin registrarse')
            ->assertSee(route('about'));
    }

    public function test_logged_in_users_go_straight_to_their_games(): void
    {
        $this->actingAs($this->guest())->get('/')->assertRedirect(route('games.index'));
    }

    public function test_about_page_is_public(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('Privacidad');
    }

    public function test_missing_pages_show_the_custom_404(): void
    {
        $this->get('/esto-no-existe')->assertNotFound()->assertSee('Aquí no hay nada');
    }

    // ---- Cuentas de invitado ----

    public function test_trying_without_signing_up_creates_a_guest_with_12_tributes(): void
    {
        $this->post(route('guest.store'))->assertRedirect(route('games.index'));

        $guest = auth()->user();
        $this->assertNotNull($guest);
        $this->assertTrue($guest->is_guest);
        $this->assertNull($guest->email);   // ningún dato personal
        $this->assertCount(12, $guest->characters);
    }

    public function test_a_guest_can_play(): void
    {
        $guest = $this->guest();

        $this->actingAs($guest)
            ->post(route('games.store'), ['characters' => $guest->characters()->pluck('id')->all()])
            ->assertRedirect();

        $this->assertSame(1, $guest->games()->count());
    }

    public function test_a_guest_who_logs_out_is_deleted_with_everything(): void
    {
        $guest = $this->guest();
        app(PlayGame::class)->handle($guest, $guest->characters()->get(), 42);

        $this->actingAs($guest)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertModelMissing($guest);
        $this->assertDatabaseMissing('characters', ['user_id' => $guest->id]);
        $this->assertDatabaseMissing('games', ['user_id' => $guest->id]);
    }

    public function test_a_registered_user_who_logs_out_keeps_the_account(): void
    {
        $demo = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();

        $this->actingAs($demo)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertModelExists($demo);
    }

    public function test_visiting_updates_when_the_user_was_last_seen(): void
    {
        $guest = $this->guest();
        $guest->forceFill(['last_seen_at' => now()->subHours(3)])->save();

        $this->actingAs($guest)->get(route('games.index'))->assertOk();

        $this->assertTrue($guest->fresh()->last_seen_at->gt(now()->subMinute()));
    }

    // ---- Limpieza de invitados ----

    public function test_prune_deletes_only_guests_that_stopped_coming(): void
    {
        Storage::fake('public');
        $old = $this->guest();
        $recent = $this->guest();
        $demo = User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail();

        // El invitado antiguo tenía un personaje con foto
        $this->actingAs($old)->put(route('characters.update', $old->characters()->first()), [
            'name' => 'Con foto', 'gender' => 'female',
            'district_id' => $old->characters()->first()->district_id,
            'image' => UploadedFile::fake()->image('foto.jpg'),
        ]);
        $photo = $old->characters()->whereNotNull('image_path')->value('image_path');
        Storage::disk('public')->assertExists($photo);

        $old->forceFill(['last_seen_at' => now()->subDays(40)])->save();
        $demo->forceFill(['last_seen_at' => now()->subDays(400)])->save();   // no es invitado: no se toca

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($demo);
        Storage::disk('public')->assertMissing($photo);
    }

    // ---- Límites ----

    public function test_there_is_a_maximum_of_characters_per_account(): void
    {
        config(['juegos.limits.characters' => 12]);
        $guest = $this->guest();

        $this->actingAs($guest)->post(route('characters.store'), [
            'name' => 'La número 13', 'gender' => 'female', 'district_id' => $guest->characters()->value('district_id'),
        ])->assertSessionHas('error');

        $this->assertSame(12, $guest->characters()->count());
    }

    public function test_there_is_a_maximum_of_saved_games_per_account(): void
    {
        config(['juegos.limits.games' => 1]);
        $guest = $this->guest();
        app(PlayGame::class)->handle($guest, $guest->characters()->get(), 1);

        $this->actingAs($guest)
            ->post(route('games.store'), ['characters' => $guest->characters()->pluck('id')->all()])
            ->assertSessionHas('error');

        $this->assertSame(1, $guest->games()->count());
    }

    // ---- Crear cuentas desde la terminal ----

    public function test_admin_accounts_can_be_created_from_the_terminal(): void
    {
        $this->artisan('user:create', ['email' => 'yo@ejemplo.com', '--name' => 'Yo', '--admin' => true])
            ->expectsQuestion('Contraseña (al menos 8 caracteres)', 'una-contraseña-larga')
            ->expectsQuestion('Repite la contraseña', 'una-contraseña-larga')
            ->assertSuccessful();

        $user = User::where('email', 'yo@ejemplo.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertCount(12, $user->characters);
    }
}
