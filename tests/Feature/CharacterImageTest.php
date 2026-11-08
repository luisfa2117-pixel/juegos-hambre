<?php

namespace Tests\Feature;

use App\Actions\PlayGame;
use App\Models\Character;
use App\Models\District;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterImageTest extends TestCase
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

        // Un disco "de mentira" en memoria: los tests no llenan tu carpeta storage de fotos
        Storage::fake('public');
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kira',
            'gender' => 'female',
            'district_id' => District::where('slug', 'pacifico_tecno')->value('id'),
        ], $overrides);
    }

    private function createKiraWithPhoto(): Character
    {
        $this->post(route('characters.store'), $this->data([
            'image' => UploadedFile::fake()->image('kira.jpg', 400, 400),
        ]));

        return Character::where('name', 'Kira')->firstOrFail();
    }

    public function test_photo_is_stored_when_creating(): void
    {
        $kira = $this->createKiraWithPhoto();

        $this->assertNotNull($kira->image_path);
        Storage::disk('public')->assertExists($kira->image_path);
    }

    public function test_photo_is_cropped_and_reduced_to_256_pixels(): void
    {
        $this->post(route('characters.store'), $this->data([
            'image' => UploadedFile::fake()->image('panoramica.jpg', 1600, 900),
        ]));

        $path = Character::where('name', 'Kira')->value('image_path');
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([256, 256], [$width, $height]);
    }

    public function test_photo_is_optional(): void
    {
        $this->post(route('characters.store'), $this->data())->assertSessionHasNoErrors();

        $this->assertNull(Character::where('name', 'Kira')->value('image_path'));
    }

    public function test_replacing_the_photo_deletes_the_old_file(): void
    {
        $kira = $this->createKiraWithPhoto();
        $oldPath = $kira->image_path;

        $this->put(route('characters.update', $kira), $this->data([
            'image' => UploadedFile::fake()->image('kira-nueva.png'),
        ]))->assertSessionHasNoErrors();

        $kira->refresh();
        $this->assertNotSame($oldPath, $kira->image_path);
        Storage::disk('public')->assertExists($kira->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_editing_without_a_new_photo_keeps_the_current_one(): void
    {
        $kira = $this->createKiraWithPhoto();
        $path = $kira->image_path;

        $this->put(route('characters.update', $kira), $this->data(['name' => 'Kira Voss']));

        $this->assertSame($path, $kira->fresh()->image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_photo_can_be_removed(): void
    {
        $kira = $this->createKiraWithPhoto();
        $path = $kira->image_path;

        $this->put(route('characters.update', $kira), $this->data(['remove_image' => '1']));

        $this->assertNull($kira->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_the_character_deletes_its_photo(): void
    {
        $kira = $this->createKiraWithPhoto();
        $path = $kira->image_path;

        $this->delete(route('characters.destroy', $kira));

        Storage::disk('public')->assertMissing($path);
    }

    public function test_rejects_files_that_are_not_images(): void
    {
        $this->post(route('characters.store'), $this->data([
            'image' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('characters', ['name' => 'Kira']);
    }

    public function test_rejects_photos_larger_than_2_mb(): void
    {
        $this->post(route('characters.store'), $this->data([
            'image' => UploadedFile::fake()->image('enorme.jpg')->size(3000),   // en KB
        ]))->assertSessionHasErrors(['image' => 'La foto puede pesar como mucho 2 MB.']);
    }

    public function test_photo_appears_in_lists_and_games(): void
    {
        $kira = $this->createKiraWithPhoto();

        $this->get(route('characters.index'))->assertSee($kira->image_url);

        $game = app(PlayGame::class)->handle($this->user, Character::all(), 42);
        $this->get(route('games.show', [$game, 'round' => $game->total_rounds]))->assertSee($kira->image_url);
    }
}
