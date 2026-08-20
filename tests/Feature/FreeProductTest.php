<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book can be explicitly marked as free.
     */
    public function test_book_can_be_marked_as_free(): void
    {
        $book = Book::factory()->create([
            'is_free' => true,
            'price' => 0,
        ]);

        $this->assertTrue($book->is_free);
    }

    /**
     * A paid book is not free.
     */
    public function test_paid_book_is_not_free(): void
    {
        $book = Book::factory()->create([
            'is_free' => false,
            'price' => 6.99,
        ]);

        $this->assertFalse($book->is_free);
    }

    /**
     * A free audiobook is recognized correctly.
     */
    public function test_audiobook_can_be_marked_as_free(): void
    {
        $audiobook = Audiobook::factory()->create([
            'is_free' => true,
            'price' => 0,
        ]);

        $this->assertTrue($audiobook->is_free);
    }

    /**
     * A paid audiobook is not free.
     */
    public function test_paid_audiobook_is_not_free(): void
    {
        $audiobook = Audiobook::factory()->create([
            'is_free' => false,
            'price' => 9.99,
        ]);

        $this->assertFalse($audiobook->is_free);
    }

    /**
     * A free course is recognized correctly.
     */
    public function test_course_can_be_marked_as_free(): void
    {
        $course = Course::factory()->create([
            'is_free' => true,
            'price' => 0,
        ]);

        $this->assertTrue($course->is_free);
    }

    /**
     * A paid course is not free.
     */
    public function test_paid_course_is_not_free(): void
    {
        $course = Course::factory()->create([
            'is_free' => false,
            'price' => 49.99,
        ]);

        $this->assertFalse($course->is_free);
    }

    /**
     * The is_free attribute is cast to boolean on books.
     */
    public function test_book_is_free_is_cast_to_boolean(): void
    {
        $book = Book::factory()->create([
            'is_free' => 1,
        ]);

        $this->assertIsBool($book->is_free);
        $this->assertTrue($book->is_free);
    }

    /**
     * The is_free attribute is cast to boolean on audiobooks.
     */
    public function test_audiobook_is_free_is_cast_to_boolean(): void
    {
        $audiobook = Audiobook::factory()->create([
            'is_free' => 1,
        ]);

        $this->assertIsBool($audiobook->is_free);
        $this->assertTrue($audiobook->is_free);
    }

    /**
     * The is_free attribute is cast to boolean on courses.
     */
    public function test_course_is_free_is_cast_to_boolean(): void
    {
        $course = Course::factory()->create([
            'is_free' => 1,
        ]);

        $this->assertIsBool($course->is_free);
        $this->assertTrue($course->is_free);
    }

    /**
     * A product is not considered free merely because its price is zero.
     *
     * The explicit is_free flag is authoritative.
     */
    public function test_zero_price_does_not_automatically_make_product_free(): void
    {
        $book = Book::factory()->create([
            'is_free' => false,
            'price' => 0,
        ]);

        $this->assertFalse($book->is_free);
    }

    /**
     * A product can be explicitly free even when a price value exists.
     *
     * This keeps the commercial rule explicit and prevents
     * accidental dependence on price alone.
     */
    public function test_explicit_free_flag_is_authoritative(): void
    {
        $book = Book::factory()->create([
            'is_free' => true,
            'price' => 6.99,
        ]);

        $this->assertTrue($book->is_free);
        $this->assertSame(
            6.99,
            (float) $book->price
        );
    }
}