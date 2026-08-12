<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Review;
use App\Models\User;
use App\Services\BookRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookRatingStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book with no reviews has zero rating statistics.
     */
    public function test_book_with_no_reviews_has_zero_rating_statistics(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(0.0, (float) $book->average_rating);
        $this->assertSame(0, $book->reviews_count);
    }

    /**
     * Rating statistics are calculated correctly from reviews.
     */
    public function test_rating_statistics_are_calculated_correctly(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 4,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(4.0, (float) $book->average_rating);
        $this->assertSame(3, $book->reviews_count);
    }

    /**
     * Adding another review updates the rating statistics.
     */
    public function test_adding_review_updates_rating_statistics(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(4.0, (float) $book->average_rating);
        $this->assertSame(2, $book->reviews_count);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(4.33, (float) $book->average_rating);
        $this->assertSame(3, $book->reviews_count);
    }

    /**
     * Updating a review recalculates the book rating.
     */
    public function test_updating_review_recalculates_rating_statistics(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        $review = Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(4.0, (float) $book->average_rating);
        $this->assertSame(2, $book->reviews_count);

        $review->update([
            'rating' => 5,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(5.0, (float) $book->average_rating);
        $this->assertSame(2, $book->reviews_count);
    }

    /**
     * Deleting a review recalculates the book rating.
     */
    public function test_deleting_review_recalculates_rating_statistics(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        $review = Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);

        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(4.0, (float) $book->average_rating);
        $this->assertSame(2, $book->reviews_count);

        $review->delete();

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(5.0, (float) $book->average_rating);
        $this->assertSame(1, $book->reviews_count);
    }

    /**
     * Soft-deleted reviews are excluded from rating statistics.
     */
    public function test_soft_deleted_reviews_are_excluded_from_statistics(): void
    {
        $book = Book::factory()->create([
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        $activeReview = Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        $deletedReview = Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 1,
        ]);

        $deletedReview->delete();

        app(BookRatingService::class)->refresh($book);

        $book->refresh();

        $this->assertSame(5.0, (float) $book->average_rating);
        $this->assertSame(1, $book->reviews_count);

        $this->assertDatabaseHas('reviews', [
            'id' => $activeReview->id,
            'deleted_at' => null,
        ]);
    }
}