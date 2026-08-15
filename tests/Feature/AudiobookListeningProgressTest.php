<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookChapter;
use App\Models\AudiobookListeningProgress;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookListeningProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Listening progress belongs to a user.
     */
    public function test_progress_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 5.00,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $this->assertTrue(
            $progress->user->is($user)
        );
    }

    /**
     * Listening progress belongs to an audiobook.
     */
    public function test_progress_belongs_to_audiobook(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 5.00,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $this->assertTrue(
            $progress->audiobook->is($audiobook)
        );
    }

    /**
     * Listening progress belongs to the current chapter.
     */
    public function test_progress_belongs_to_chapter(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $chapter = AudiobookChapter::create([
            'audiobook_id' => $audiobook->id,
            'title' => 'Introduction',
            'description' => 'Introduction to the audiobook.',
            'track_number' => 1,
            'audio_file' => 'audiobooks/test/chapter-01.mp3',
            'duration_seconds' => 600,
            'status' => 'active',
            'is_preview' => true,
            'published_at' => now(),
        ]);

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'audiobook_chapter_id' => $chapter->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 20.00,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $this->assertTrue(
            $progress->chapter->is($chapter)
        );
    }

    /**
     * Listening progress has a UUID.
     */
    public function test_progress_has_uuid(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 5.00,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $this->assertNotNull($progress->uuid);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $progress->uuid
        );
    }

    /**
     * Listening progress values are cast correctly.
     */
    public function test_progress_values_are_cast_correctly(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 125,
            'listened_seconds' => 300,
            'progress_percent' => 8.50,
            'is_completed' => false,
            'last_listened_at' => now(),
        ]);

        $this->assertSame(
            125,
            $progress->position_seconds
        );

        $this->assertSame(
            300,
            $progress->listened_seconds
        );

        $this->assertSame(
            '8.50',
            $progress->progress_percent
        );

        $this->assertFalse(
            $progress->is_completed
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $progress->last_listened_at
        );
    }

    /**
     * A new progress record has no progress.
     */
    public function test_progress_can_have_no_saved_position(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 0,
            'listened_seconds' => 0,
            'progress_percent' => 0,
            'is_completed' => false,
        ]);

        $this->assertFalse(
            $progress->hasProgress()
        );
    }

    /**
     * Progress is detected when a playback position exists.
     */
    public function test_progress_is_detected_from_position(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 125,
            'listened_seconds' => 125,
            'progress_percent' => 5.00,
            'is_completed' => false,
        ]);

        $this->assertTrue(
            $progress->hasProgress()
        );
    }

    /**
     * Progress is detected when listened seconds exist.
     */
    public function test_progress_is_detected_from_listened_seconds(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 0,
            'listened_seconds' => 120,
            'progress_percent' => 3.00,
            'is_completed' => false,
        ]);

        $this->assertTrue(
            $progress->hasProgress()
        );
    }

    /**
     * Playback position can be converted to minutes.
     */
    public function test_position_can_be_converted_to_minutes(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 125,
            'listened_seconds' => 125,
            'progress_percent' => 5.00,
            'is_completed' => false,
        ]);

        $this->assertSame(
            2.08,
            $progress->positionInMinutes()
        );
    }

    /**
     * An incomplete audiobook is not completed.
     */
    public function test_incomplete_audiobook_is_not_completed(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 5.00,
            'is_completed' => false,
        ]);

        $this->assertFalse(
            $progress->isCompleted()
        );
    }

    /**
     * A completed audiobook is reported as completed.
     */
    public function test_completed_audiobook_is_completed(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        $progress = AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 3600,
            'listened_seconds' => 3600,
            'progress_percent' => 100.00,
            'is_completed' => true,
            'last_listened_at' => now(),
        ]);

        $this->assertTrue(
            $progress->isCompleted()
        );
    }

    /**
     * A customer can have only one progress record
     * for the same audiobook.
     */
    public function test_user_can_have_only_one_progress_record_per_audiobook(): void
    {
        $user = User::factory()->create();
        $audiobook = $this->createAudiobook();

        AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 120,
            'listened_seconds' => 120,
            'progress_percent' => 5.00,
            'is_completed' => false,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        AudiobookListeningProgress::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'position_seconds' => 300,
            'listened_seconds' => 300,
            'progress_percent' => 10.00,
            'is_completed' => false,
        ]);
    }

    /**
     * Create an audiobook for testing.
     */
    private function createAudiobook(): Audiobook
    {
        $book = Book::factory()->create();

        return Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);
    }
}