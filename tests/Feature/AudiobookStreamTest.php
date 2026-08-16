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

class AudiobookStreamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An unauthenticated user cannot stream an audiobook.
     */
    public function test_unauthenticated_user_cannot_stream(): void
    {
        $chapter = $this->createChapter();

        $response = $this->getJson(
            "/api/audiobook-chapters/{$chapter->uuid}/stream"
        );

        $response->assertUnauthorized();
    }

    /**
     * An authenticated user without an entitlement cannot stream.
     */
    public function test_user_without_entitlement_cannot_stream(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertForbidden();
    }

    /**
     * A user with an entitlement but without streaming
     * permission cannot stream.
     */
    public function test_user_without_stream_permission_cannot_stream(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $this->createEntitlement(
            $user,
            $chapter->audiobook,
            [
                'can_stream' => false,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertForbidden();
    }

    /**
     * An expired entitlement cannot stream.
     */
    public function test_expired_entitlement_cannot_stream(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $this->createEntitlement(
            $user,
            $chapter->audiobook,
            [
                'can_stream' => true,
                'expires_at' => now()->subMinute(),
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertForbidden();
    }

    /**
     * A revoked entitlement cannot stream.
     */
    public function test_revoked_entitlement_cannot_stream(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $this->createEntitlement(
            $user,
            $chapter->audiobook,
            [
                'can_stream' => true,
                'revoked_at' => now(),
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertForbidden();
    }

    /**
     * An inactive entitlement cannot stream.
     */
    public function test_inactive_entitlement_cannot_stream(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $this->createEntitlement(
            $user,
            $chapter->audiobook,
            [
                'can_stream' => true,
                'status' => 'inactive',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertForbidden();
    }

    /**
     * An inactive audiobook cannot be streamed.
     */
    public function test_inactive_audiobook_cannot_be_streamed(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter([
            'audiobook_status' => 'inactive',
        ]);

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertNotFound();
    }

    /**
     * A draft audiobook cannot be streamed.
     */
    public function test_draft_audiobook_cannot_be_streamed(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter([
            'audiobook_status' => 'draft',
        ]);

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertNotFound();
    }

    /**
     * An inactive chapter cannot be streamed.
     */
    public function test_inactive_chapter_cannot_be_streamed(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter([
            'chapter_status' => 'inactive',
        ]);

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertNotFound();
    }

    /**
     * A chapter scheduled for future publication cannot be streamed.
     */
    public function test_future_chapter_cannot_be_streamed(): void
    {
        $user = User::factory()->create();

        $chapter = $this->createChapter([
            'published_at' => now()->addHour(),
        ]);

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertNotFound();
    }

    /**
     * An entitled user can stream an audiobook chapter.
     */
    public function test_entitled_user_can_stream(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            'test audio content'
        );

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertStatus(200);

        $response->assertHeader(
            'Content-Type',
            'audio/mpeg'
        );

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $response->assertHeader(
            'Content-Disposition',
            'inline'
        );

        /*
         * Laravel/Symfony may normalize the order of
         * Cache-Control directives.
         *
         * We therefore verify that both required
         * directives are present rather than checking
         * their exact order.
         */
        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );

        $this->assertSame(
            'test audio content',
            $response->streamedContent()
        );
    }

    /**
     * A range request returns partial content.
     */
    public function test_range_request_returns_partial_content(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $audio = 'abcdefghijklmnopqrstuvwxyz';

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            $audio
        );

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->withHeaders([
                'Range' => 'bytes=0-4',
            ])
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertStatus(206);

        $response->assertHeader(
            'Content-Range',
            'bytes 0-4/26'
        );

        $response->assertHeader(
            'Content-Length',
            '5'
        );

        $response->assertHeader(
            'Accept-Ranges',
            'bytes'
        );

        $this->assertSame(
            'abcde',
            $response->streamedContent()
        );
    }

    /**
     * An open-ended range returns content from the requested
     * position to the end of the file.
     */
    public function test_open_ended_range_request_works(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $audio = 'abcdefghijklmnopqrstuvwxyz';

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            $audio
        );

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->withHeaders([
                'Range' => 'bytes=20-',
            ])
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertStatus(206);

        $response->assertHeader(
            'Content-Range',
            'bytes 20-25/26'
        );

        $response->assertHeader(
            'Content-Length',
            '6'
        );

        $this->assertSame(
            'uvwxyz',
            $response->streamedContent()
        );
    }

    /**
     * A suffix range returns the final requested number of bytes.
     */
    public function test_suffix_range_request_works(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $audio = 'abcdefghijklmnopqrstuvwxyz';

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            $audio
        );

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->withHeaders([
                'Range' => 'bytes=-5',
            ])
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertStatus(206);

        $response->assertHeader(
            'Content-Range',
            'bytes 21-25/26'
        );

        $response->assertHeader(
            'Content-Length',
            '5'
        );

        $this->assertSame(
            'vwxyz',
            $response->streamedContent()
        );
    }

    /**
     * An invalid range returns 416.
     */
    public function test_invalid_range_returns_416(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        Storage::disk('audiobooks')->put(
            $chapter->audio_file,
            'abcdefghijklmnopqrstuvwxyz'
        );

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->withHeaders([
                'Range' => 'bytes=100-200',
            ])
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertStatus(416);

        $response->assertHeader(
            'Content-Range',
            'bytes */26'
        );
    }

    /**
     * A missing audio file returns 404.
     */
    public function test_missing_audio_file_returns_404(): void
    {
        Storage::fake('audiobooks');

        $user = User::factory()->create();

        $chapter = $this->createChapter();

        $this->createEntitlement(
            $user,
            $chapter->audiobook
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->get(
                "/api/audiobook-chapters/{$chapter->uuid}/stream"
            );

        $response->assertNotFound();
    }

    /**
     * Create an audiobook chapter for testing.
     */
    private function createChapter(
        array $overrides = []
    ): AudiobookChapter {
        $book = Book::factory()->create();

        $audiobookStatus = $overrides['audiobook_status']
            ?? 'active';

        $chapterStatus = $overrides['chapter_status']
            ?? 'active';

        $publishedAt = $overrides['published_at']
            ?? now()->subMinute();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => $audiobookStatus,
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);

        return AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Test Chapter',
            'description' => 'Test audiobook chapter.',
            'track_number' => 1,
            'audio_file' => 'test/chapter.mp3',
            'duration_seconds' => 600,
            'status' => $chapterStatus,
            'is_preview' => false,
            'published_at' => $publishedAt,
        ]);
    }

    /**
     * Create an audiobook entitlement for testing.
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