<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('games.index'))->assertRedirect(route('login'));
        $this->get(route('characters.index'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_registering_creates_the_account_with_8_characters_and_logs_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Luis',
            'email' => 'luis@ejemplo.com',
            'password' => 'contraseña-larga',
            'password_confirmation' => 'contraseña-larga',
        ])->assertRedirect(route('games.index'));

        $user = User::where('email', 'luis@ejemplo.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(8, $user->characters);
        $this->assertNotSame('contraseña-larga', $user->password);   // guardada cifrada
        $this->assertFalse($user->is_admin);
    }

    public function test_cannot_become_admin_by_adding_a_field_to_the_form(): void
    {
        $this->post(route('register'), [
            'name' => 'Listillo',
            'email' => 'listillo@ejemplo.com',
            'password' => 'contraseña-larga',
            'password_confirmation' => 'contraseña-larga',
            'is_admin' => '1',
        ]);

        $this->assertFalse(User::where('email', 'listillo@ejemplo.com')->firstOrFail()->is_admin);
    }

    public function test_registration_validates_the_data(): void
    {
        $this->post(route('register'), [
            'name' => '',
            'email' => CharacterSeeder::DEMO_EMAIL,   // ya existe
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_logs_in_with_the_right_password(): void
    {
        $this->post(route('login'), ['email' => CharacterSeeder::DEMO_EMAIL, 'password' => 'password'])
            ->assertRedirect(route('games.index'));

        $this->assertAuthenticated();
    }

    public function test_does_not_log_in_with_a_wrong_password(): void
    {
        $this->post(route('login'), ['email' => CharacterSeeder::DEMO_EMAIL, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
    }

    public function test_logs_out(): void
    {
        $this->actingAs(User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail());

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logged_in_users_cannot_see_the_login_page(): void
    {
        $this->actingAs(User::where('email', CharacterSeeder::DEMO_EMAIL)->firstOrFail());

        $this->get(route('login'))->assertRedirect();
    }
}
