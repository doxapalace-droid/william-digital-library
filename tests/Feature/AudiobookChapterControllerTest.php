<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookChapterControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A public audiobook returns its active chapters.
     */
    public function test_public_audiobook_returns_active_chapters(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'description' => 'First chapter.',
            'track_number' => 1,
            'audio_file' => 'audiobooks/chapter-1.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => true,
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.title',
                'Chapter One'
            )
            ->assertJsonPath(
                'data.0.track_number',
                1
            );
    }

    /**
     * Inactive chapters are not returned.
     */
    public function test_inactive_chapters_are_not_returned(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Inactive Chapter',
            'track_number' => 1,
            'audio_file' => 'test.mp3',
            'duration_seconds' => 300,
            'status' => 'inactive',
            'is_preview' => false,
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }

    /**
     * Future chapters are not returned.
     */
    public function test_future_chapters_are_not_returned(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Future Chapter',
            'track_number' => 1,
            'audio_file' => 'test.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => false,
            'published_at' => now()->addHour(),
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }

    /**
     * Chapters are returned in track order.
     */
    public function test_chapters_are_ordered_by_track_number(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter Three',
            'track_number' => 3,
            'audio_file' => 'three.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'track_number' => 1,
            'audio_file' => 'one.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter Two',
            'track_number' => 2,
            'audio_file' => 'two.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.track_number',
                1
            )
            ->assertJsonPath(
                'data.1.track_number',
                2
            )
            ->assertJsonPath(
                'data.2.track_number',
                3
            );
    }

    /**
     * Inactive audiobook cannot expose chapters.
     */
    public function test_inactive_audiobook_returns_not_found(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'inactive',
            'duration_seconds' => 3600,
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response->assertNotFound();
    }

    /**
     * Draft audiobook cannot expose chapters.
     */
    public function test_draft_audiobook_returns_not_found(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'draft',
            'duration_seconds' => 3600,
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response->assertNotFound();
    }

    /**
     * Future audiobook cannot expose chapters.
     */
    public function test_future_audiobook_returns_not_found(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->addHour(),
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response->assertNotFound();
    }

    /**
     * Public chapter response must not expose
     * the private audio file path.
     */
    public function test_public_response_does_not_expose_audio_file(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'track_number' => 1,
            'audio_file' => 'private/audiobooks/secret.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.0.audio_file'
            )
            ->assertJsonMissing(
                [
                    'audio_file' =>
                        'private/audiobooks/secret.mp3',
                ]
            );
    }

    /**
     * Preview chapters are identified correctly.
     */
    public function test_preview_chapter_is_identified(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Preview Chapter',
            'track_number' => 1,
            'audio_file' => 'preview.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => true,
        ]);

        $response = $this->getJson(
            "/api/audiobooks/{$audiobook->uuid}/chapters"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.is_preview',
                true
            );
    }
}