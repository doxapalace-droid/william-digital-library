<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookChapterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An audiobook chapter belongs to an audiobook.
     */
    public function test_chapter_belongs_to_audiobook(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Audiobook edition.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'description' => 'Introduction to the book.',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/introduction.mp3',
            'duration_seconds' => 600,
            'status' => 'active',
            'is_preview' => true,
            'published_at' => now(),
        ]);

        $this->assertTrue(
            $chapter->audiobook->is($audiobook)
        );
    }

    /**
     * An audiobook can have multiple chapters.
     */
    public function test_audiobook_can_have_multiple_chapters(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => true,
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'track_number' => 2,
            'audio_file' => 'audiobooks/test/02.mp3',
            'duration_seconds' => 900,
            'status' => 'active',
            'is_preview' => false,
        ]);

        $this->assertCount(
            2,
            $audiobook->chapters
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
            'duration_seconds' => 1800,
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter Two',
            'track_number' => 2,
            'audio_file' => 'audiobooks/test/02.mp3',
            'duration_seconds' => 600,
            'status' => 'active',
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        $chapters = $audiobook->chapters;

        $this->assertSame(
            1,
            $chapters->first()->track_number
        );

        $this->assertSame(
            2,
            $chapters->last()->track_number
        );
    }

    /**
     * Chapter has a UUID.
     */
    public function test_chapter_has_uuid(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        $this->assertNotNull(
            $chapter->uuid
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $chapter->uuid
        );
    }

    /**
     * Chapter values are cast correctly.
     */
    public function test_chapter_values_are_cast_correctly(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 605,
            'status' => 'active',
            'is_preview' => true,
            'published_at' => now(),
        ]);

        $this->assertSame(
            1,
            $chapter->track_number
        );

        $this->assertSame(
            605,
            $chapter->duration_seconds
        );

        $this->assertTrue(
            $chapter->is_preview
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $chapter->published_at
        );
    }

    /**
     * An active chapter is active.
     */
    public function test_active_chapter_is_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue(
            $chapter->isActive()
        );
    }

    /**
     * An inactive chapter is not active.
     */
    public function test_inactive_chapter_is_not_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'inactive',
            'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $chapter->isActive()
        );
    }

    /**
     * A future-published chapter is not active.
     */
    public function test_future_published_chapter_is_not_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'published_at' => now()->addHour(),
        ]);

        $this->assertFalse(
            $chapter->isActive()
        );
    }

    /**
     * An active preview chapter is available as a preview.
     */
    public function test_active_preview_chapter_is_available_as_preview(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue(
            $chapter->isPreviewAvailable()
        );
    }

    /**
     * A non-preview chapter is not available as a preview.
     */
    public function test_non_preview_chapter_is_not_available_as_preview(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 900,
            'status' => 'active',
            'is_preview' => false,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $chapter->isPreviewAvailable()
        );
    }

    /**
     * Chapter duration can be converted to minutes.
     */
    public function test_chapter_duration_can_be_converted_to_minutes(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 3660,
            'status' => 'active',
        ]);

        $this->assertSame(
            61.0,
            $chapter->durationInMinutes()
        );
    }

    /**
     * Duplicate track numbers are not allowed within
     * the same audiobook.
     */
    public function test_duplicate_track_number_is_rejected(): void
    {
        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Chapter One',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Another Chapter One',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/01b.mp3',
            'duration_seconds' => 400,
            'status' => 'active',
        ]);
    }

    /**
     * The same track number can be used in different audiobooks.
     */
    public function test_same_track_number_can_exist_in_different_audiobooks(): void
    {
        $bookOne = Book::factory()->create();
        $bookTwo = Book::factory()->create();

        $audiobookOne = Audiobook::create([
            'book_id' => $bookOne->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $audiobookTwo = Audiobook::create([
            'book_id' => $bookTwo->id,
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 2400,
        ]);

        $chapterOne = AudiobookChapter::create([
            'audiobook_id' => $audiobookOne->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/one/01.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
        ]);

        $chapterTwo = AudiobookChapter::create([
            'audiobook_id' => $audiobookTwo->id,
            'title' => 'Introduction',
            'track_number' => 1,
            'audio_file' => 'audiobooks/two/01.mp3',
            'duration_seconds' => 400,
            'status' => 'active',
        ]);

        $this->assertNotSame(
            $chapterOne->id,
            $chapterTwo->id
        );

        $this->assertSame(
            1,
            $chapterOne->track_number
        );

        $this->assertSame(
            1,
            $chapterTwo->track_number
        );
    }
}