<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudiobookDownloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unauthenticated users cannot download.
     */
    public function test_unauthenticated_user_cannot_download(): void
    {
        $audiobook = $this->createAudiobook();

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/download"
        );

        $response->assertUnauthorized();
    }

    /**
     * User without entitlement cannot download.
     */
    public function test_user_without_entitlement_cannot_download(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertForbidden();
    }

    /**
     * Entitlement without download permission cannot download.
     */
    public function test_user_without_download_permission_cannot_download(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertForbidden();
    }

    /**
     * Expired entitlement cannot download.
     */
    public function test_expired_entitlement_cannot_download(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertForbidden();
    }

    /**
     * Revoked entitlement cannot download.
     */
    public function test_revoked_entitlement_cannot_download(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now()->subDay(),
            'revoked_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertForbidden();
    }

    /**
     * Inactive audiobook cannot be downloaded.
     */
    public function test_inactive_audiobook_cannot_be_downloaded(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'status' => 'inactive',
        ]);

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertNotFound();
    }

    /**
     * Missing audio file returns 404.
     */
    public function test_missing_audio_file_returns_not_found(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertNotFound();
    }

    /**
     * Entitled customer can download.
     */
    public function test_entitled_user_can_download(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'description' => 'First chapter.',
            'track_number' => 1,
            'audio_file' => 'audiobooks/chapter-1.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => false,
            'published_at' => now()->subMinute(),
        ]);

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            'fake audio content'
        );

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(
                "/api/audiobooks/{$audiobook->uuid}/download"
            );

        $response->assertOk();

        /*
         * The response must force the browser to download
         * the file rather than display it inline.
         */
        $response->assertHeader(
            'Content-Disposition'
        );

        /*
         * Laravel/Symfony may normalize the order of
         * Cache-Control directives.
         *
         * We therefore verify that both required
         * security directives are present rather than
         * requiring a particular order.
         */
        $cacheControl = $response->headers->get(
            'Cache-Control'
        );

        $this->assertNotNull(
            $cacheControl
        );

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );

        /*
         * Prevent browsers from MIME-sniffing the response.
         */
        $response->assertHeader(
            'X-Content-Type-Options',
            'nosniff'
        );
    }

    /**
     * Create a standard active audiobook.
     */
    private function createAudiobook(
        array $overrides = []
    ): Audiobook {
        $book = Book::factory()->create();

        return Audiobook::create(
            array_merge(
                [
                    'book_id' => $book->id,
                    'description' => 'Test audiobook.',
                    'price' => 25.00,
                    'currency' => 'USD',
                    'status' => 'active',
                    'duration_seconds' => 3600,
                    'published_at' => now()->subMinute(),
                ],
                $overrides
            )
        );
    }
}