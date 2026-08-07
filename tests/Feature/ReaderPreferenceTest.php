<?php

namespace Tests\Feature;

use App\Models\ReaderPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReaderPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest users cannot view reader preferences.
     */
    public function test_guest_cannot_view_reader_preferences(): void
    {
        $this->getJson('/api/reader-preferences')
            ->assertUnauthorized();
    }

    /**
     * An authenticated user can view their reader preferences.
     */
    public function test_authenticated_user_can_view_reader_preferences(): void
    {
        $user = User::factory()->create();

        ReaderPreference::create([
            'user_id' => $user->id,
            'font_size' => 18,
            'font_family' => 'serif',
            'theme' => 'dark',
            'line_spacing' => 1.8,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reader-preferences');

        $response
            ->assertOk()
            ->assertJsonPath('data.font_size', 18)
            ->assertJsonPath('data.font_family', 'serif')
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.line_spacing', 1.8);
    }

    /**
     * An authenticated user can save reader preferences.
     */
    public function test_authenticated_user_can_save_reader_preferences(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/reader-preferences', [
            'font_size' => 20,
            'font_family' => 'sans-serif',
            'theme' => 'sepia',
            'line_spacing' => 1.6,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.font_size', 20)
            ->assertJsonPath('data.font_family', 'sans-serif')
            ->assertJsonPath('data.theme', 'sepia')
            ->assertJsonPath('data.line_spacing', 1.6);

        $this->assertDatabaseHas('reader_preferences', [
            'user_id' => $user->id,
            'font_size' => 20,
            'font_family' => 'sans-serif',
            'theme' => 'sepia',
        ]);
    }

    /**
     * Saving preferences again updates the existing record
     * instead of creating another one.
     */
    public function test_saving_preferences_updates_existing_record(): void
    {
        $user = User::factory()->create();

        ReaderPreference::create([
            'user_id' => $user->id,
            'font_size' => 16,
            'font_family' => 'serif',
            'theme' => 'light',
            'line_spacing' => 1.5,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/reader-preferences', [
            'font_size' => 22,
            'font_family' => 'sans-serif',
            'theme' => 'dark',
            'line_spacing' => 2.0,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.font_size', 22)
            ->assertJsonPath('data.theme', 'dark');

        $this->assertDatabaseCount('reader_preferences', 1);

        $this->assertDatabaseHas('reader_preferences', [
            'user_id' => $user->id,
            'font_size' => 22,
            'theme' => 'dark',
        ]);
    }

    /**
     * A user cannot see another user's preferences.
     */
    public function test_user_cannot_see_another_users_preferences(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ReaderPreference::create([
            'user_id' => $otherUser->id,
            'font_size' => 24,
            'font_family' => 'serif',
            'theme' => 'dark',
            'line_spacing' => 2.0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reader-preferences');

        $response->assertOk();

        $response->assertJsonMissing([
            'font_size' => 24,
        ]);
    }

    /**
     * Reader preferences must contain valid values.
     */
    public function test_reader_preferences_are_validated(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/reader-preferences', [
            'font_size' => 100,
            'font_family' => 'invalid-font',
            'theme' => 'invalid-theme',
            'line_spacing' => 10,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'font_size',
                'font_family',
                'theme',
                'line_spacing',
            ]);
    }
}