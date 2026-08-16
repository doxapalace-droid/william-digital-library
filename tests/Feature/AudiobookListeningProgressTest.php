<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\AudiobookEntitlement;
use App\Models\AudiobookListeningProgress;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookListeningProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An unauthenticated customer cannot view progress.
     */
    public function test_unauthenticated_user_cannot_view_progress(): void
    {
        $audiobook = $this->createAudiobook();

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/progress"
        );

        $response->assertUnauthorized();
    }

    /**
     * An unauthenticated customer cannot update progress.
     */
    public function test_unauthenticated_user_cannot_update_progress(): void
    {
        $audiobook = $this->createAudiobook();

        $response = $this->putJson(
            "/api/audiobooks/{$audiobook->uuid}/progress",
            [
                'position_seconds' => 120,
                'listened_seconds' => 120,
            ]
        );

        $response->assertUnauthorized();
    }

    /**
     * A customer without entitlement cannot view progress.
     */
    public function test_user_without_entitlement_cannot_view_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobooks/{$audiobook->uuid}/progress"
            );

        $response->assertForbidden();
    }

    /**
     * A customer without entitlement cannot update progress.
     */
    public function test_user_without_entitlement_cannot_update_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'position_seconds' => 120,
                    'listened_seconds' => 120,
                ]
            );

        $response->assertForbidden();
    }

    /**
     * An entitled customer can view progress.
     */
    public function test_entitled_user_can_view_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $chapter = $this->createChapter($audiobook);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'audiobook_chapter_id' => $chapter->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 10,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobooks/{$audiobook->uuid}/progress"
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data.uuid',
            $progress->uuid
        );

        $response->assertJsonPath(
            'data.position_seconds',
            120
        );

        $response->assertJsonPath(
            'data.listened_seconds',
            120
        );

        $response->assertJsonPath(
            'data.is_completed',
            false
        );
    }

    /**
     * An entitled customer receives null when no progress exists.
     */
    public function test_no_progress_returns_null(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobooks/{$audiobook->uuid}/progress"
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data',
            null
        );
    }

    /**
     * An entitled customer can create listening progress.
     */
    public function test_user_can_create_listening_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $chapter = $this->createChapter($audiobook);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'audiobook_chapter_id' => $chapter->id,
                    'position_seconds' => 120,
                    'listened_seconds' => 120,
                ]
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data.audiobook_id',
            $audiobook->id
        );

        $response->assertJsonPath(
            'data.audiobook_chapter_id',
            $chapter->id
        );

        $response->assertJsonPath(
            'data.position_seconds',
            120
        );

        $response->assertJsonPath(
            'data.listened_seconds',
            120
        );

        $this->assertDatabaseHas(
            'audiobook_listening_progress',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
                'audiobook_chapter_id' => $chapter->id,
                'position_seconds' => 120,
                'listened_seconds' => 120,
            ]
        );
    }

    /**
     * Updating progress updates the existing record.
     */
    public function test_user_can_update_existing_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $chapter = $this->createChapter($audiobook);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'audiobook_chapter_id' => $chapter->id,
            'position_seconds' => 60,
            'listened_seconds' => 60,
            'progress_percent' => 1.67,
            'is_completed' => false,
            'last_listened_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'audiobook_chapter_id' => $chapter->id,
                    'position_seconds' => 240,
                    'listened_seconds' => 240,
                ]
            );

        $response->assertOk();

        $this->assertDatabaseCount(
            'audiobook_listening_progress',
            1
        );

        $this->assertDatabaseHas(
            'audiobook_listening_progress',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
                'position_seconds' => 240,
                'listened_seconds' => 240,
            ]
        );
    }

    /**
     * Progress percentage is calculated from listened seconds.
     */
    public function test_progress_percentage_is_calculated(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'duration_seconds' => 3600,
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'listened_seconds' => 900,
                ]
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data.progress_percent',
            '25.00'
        );
    }

    /**
     * Completing the audiobook automatically sets
     * progress percentage to 100.
     */
    public function test_audiobook_is_completed_when_duration_is_reached(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'duration_seconds' => 3600,
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'listened_seconds' => 3600,
                ]
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data.progress_percent',
            '100.00'
        );

        $response->assertJsonPath(
            'data.is_completed',
            true
        );
    }

    /**
     * An invalid chapter belonging to another audiobook is rejected.
     */
    public function test_chapter_must_belong_to_audiobook(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $anotherAudiobook = $this->createAudiobook();

        $chapter = $this->createChapter(
            $anotherAudiobook
        );

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'audiobook_chapter_id' => $chapter->id,
                    'position_seconds' => 100,
                    'listened_seconds' => 100,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'audiobook_chapter_id',
        ]);
    }

    /**
     * Position cannot be negative.
     */
    public function test_position_seconds_cannot_be_negative(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/audiobooks/{$audiobook->uuid}/progress",
                [
                    'position_seconds' => -1,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'position_seconds',
        ]);
    }

    /**
     * Inactive audiobooks cannot expose progress.
     */
    public function test_inactive_audiobook_cannot_expose_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'status' => 'inactive',
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobooks/{$audiobook->uuid}/progress"
            );

        $response->assertNotFound();
    }

    /**
     * An inactive entitlement cannot access progress.
     */
    public function test_inactive_entitlement_cannot_access_progress(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $this->createEntitlement(
            $user,
            $audiobook,
            [
                'status' => 'inactive',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobooks/{$audiobook->uuid}/progress"
            );

        $response->assertForbidden();
    }

    /**
     * Create an audiobook.
     */
    private function createAudiobook(
        array $overrides = []
    ): Audiobook {
        $book = Book::factory()->create();

        return Audiobook::create(array_merge([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ], $overrides));
    }

    /**
     * Create an audiobook chapter.
     */
    private function createChapter(
        Audiobook $audiobook
    ): AudiobookChapter {
        return AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Test Chapter',
            'description' => 'Test chapter.',
            'track_number' => 1,
            'audio_file' => 'test/chapter.mp3',
            'duration_seconds' => 600,
            'status' => 'active',
            'is_preview' => false,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Create an audiobook entitlement.
     */
    private function createEntitlement(
        User $user,
        Audiobook $audiobook,
        array $overrides = []
    ): AudiobookEntitlement {
        return AudiobookEntitlement::create(array_merge([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ], $overrides));
    }
}