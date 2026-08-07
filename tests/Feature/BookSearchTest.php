<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Users can search published books by title.
     */
    public function test_user_can_search_published_books_by_title(): void
    {
        Book::factory()->create([
            'title' => 'The Power of Binding and Loosing',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        Book::factory()->create([
            'title' => 'Kingdom Leadership',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $response = $this->getJson(
            '/api/books/search?q=Binding'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'The Power of Binding and Loosing'
            );
    }

    /**
     * Search should be case insensitive.
     */
    public function test_book_search_is_case_insensitive(): void
    {
        Book::factory()->create([
            'title' => 'Kingdom Dominion',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $response = $this->getJson(
            '/api/books/search?q=kingdom'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Kingdom Dominion'
            );
    }

    /**
     * Users can search published books by author.
     */
    public function test_user_can_search_books_by_author(): void
    {
        Book::factory()->create([
            'title' => 'Book One',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        Book::factory()->create([
            'title' => 'Book Two',
            'author' => 'Another Author',
            'is_published' => true,
        ]);

        $response = $this->getJson(
            '/api/books/search?q=William'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.author',
                'William K. Danquah'
            );
    }

    /**
     * Search must never expose unpublished books.
     */
    public function test_search_does_not_return_unpublished_books(): void
    {
        Book::factory()->create([
            'title' => 'Hidden Kingdom Secrets',
            'author' => 'William K. Danquah',
            'is_published' => false,
        ]);

        Book::factory()->create([
            'title' => 'Kingdom Authority',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $response = $this->getJson(
            '/api/books/search?q=Kingdom'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Kingdom Authority'
            );
    }

    /**
     * A search with no matches returns an empty collection.
     */
    public function test_search_returns_empty_results_when_nothing_matches(): void
    {
        Book::factory()->create([
            'title' => 'Kingdom Authority',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $response = $this->getJson(
            '/api/books/search?q=NonexistentBook'
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Users can filter published books by category.
     */
    public function test_user_can_filter_books_by_category(): void
    {
        $leadership = Category::factory()->create([
            'name' => 'Leadership',
        ]);

        $prayer = Category::factory()->create([
            'name' => 'Prayer',
        ]);

        $leadershipBook = Book::factory()->create([
            'title' => 'Kingdom Leadership',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $prayerBook = Book::factory()->create([
            'title' => 'The Power of Prayer',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $leadershipBook->categories()->attach($leadership->id);
        $prayerBook->categories()->attach($prayer->id);

        $response = $this->getJson(
            "/api/books/search?category={$leadership->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Kingdom Leadership'
            );
    }

    /**
     * Search text and category filters can work together.
     */
    public function test_user_can_search_within_a_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'Kingdom Living',
        ]);

        $matchingBook = Book::factory()->create([
            'title' => 'Kingdom Dominion',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $otherBook = Book::factory()->create([
            'title' => 'Kingdom Leadership',
            'author' => 'William K. Danquah',
            'is_published' => true,
        ]);

        $matchingBook->categories()->attach($category->id);

        /*
         * Attach the second book to a different category so the test
         * clearly proves that both the text search and category filter
         * are being applied together.
         */
        $otherCategory = Category::factory()->create([
            'name' => 'Leadership',
        ]);

        $otherBook->categories()->attach($otherCategory->id);

        $response = $this->getJson(
            "/api/books/search?q=Dominion&category={$category->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Kingdom Dominion'
            );
    }

    /**
     * Search query length should be validated.
     */
    public function test_search_query_is_validated(): void
    {
        $response = $this->getJson(
            '/api/books/search?q=a'
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'q',
            ]);
    }
}