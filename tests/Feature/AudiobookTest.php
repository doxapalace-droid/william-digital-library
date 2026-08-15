<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An audiobook belongs to a book.
     */
    public function test_audiobook_belongs_to_book(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'description' => 'The audiobook edition.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        $this->assertTrue(
            $audiobook->book->is($book)
        );
    }

    /**
     * A book can have one audiobook.
     */
    public function test_book_has_one_audiobook(): void
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

        /*
         * Refresh the book so Laravel retrieves the
         * audiobook relationship from the database.
         */
        $book->refresh();

        $this->assertTrue(
            $book->audiobook->is($audiobook)
        );
    }

    /**
     * Audiobook has a UUID.
     */
    public function test_audiobook_has_uuid(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        $this->assertNotNull($audiobook->uuid);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $audiobook->uuid
        );
    }

    /**
     * Audiobook monetary and date attributes are cast correctly.
     */
    public function test_audiobook_values_are_cast_correctly(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.50,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3660,
            'published_at' => now(),
        ]);

        $this->assertSame(
            '25.50',
            $audiobook->price
        );

        $this->assertSame(
            3660,
            $audiobook->duration_seconds
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $audiobook->published_at
        );
    }

    /**
     * An active published audiobook is active.
     */
    public function test_active_published_audiobook_is_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue(
            $audiobook->isActive()
        );
    }

    /**
     * A draft audiobook is not active.
     */
    public function test_draft_audiobook_is_not_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'draft',
            'duration_seconds' => 1800,
        ]);

        $this->assertFalse(
            $audiobook->isActive()
        );
    }

    /**
     * An inactive audiobook is not active.
     */
    public function test_inactive_audiobook_is_not_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'inactive',
            'duration_seconds' => 1800,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $audiobook->isActive()
        );
    }

    /**
     * An audiobook scheduled for future publication is not active.
     */
    public function test_future_published_audiobook_is_not_active(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
            'published_at' => now()->addHour(),
        ]);

        $this->assertFalse(
            $audiobook->isActive()
        );
    }

    /**
     * An active audiobook is purchasable.
     */
    public function test_active_audiobook_is_purchasable(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);

        $this->assertTrue(
            $audiobook->isPurchasable()
        );
    }

    /**
     * A draft audiobook is not purchasable.
     */
    public function test_draft_audiobook_is_not_purchasable(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'draft',
            'duration_seconds' => 3600,
        ]);

        $this->assertFalse(
            $audiobook->isPurchasable()
        );
    }

    /**
     * Audiobook duration can be converted to minutes.
     */
    public function test_audiobook_duration_can_be_converted_to_minutes(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3660,
        ]);

        $this->assertSame(
            61.0,
            $audiobook->durationInMinutes()
        );
    }

    /**
     * Audiobook can count its chapters.
     */
    public function test_audiobook_can_count_chapters(): void
    {
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
        ]);

        $this->assertSame(
            0,
            $audiobook->chaptersCount()
        );
    }

    /**
     * A book cannot have two audiobook records.
     */
    public function test_book_cannot_have_multiple_audiobooks(): void
    {
        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $book = Book::factory()->create();

        Audiobook::create([
            'book_id' => $book->id,
            'price' => 20.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
        ]);

        Audiobook::create([
            'book_id' => $book->id,
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 2400,
        ]);
    }
}