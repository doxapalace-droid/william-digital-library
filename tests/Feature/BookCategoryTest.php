<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published book returns its assigned categories.
     */
    public function test_published_book_returns_its_categories(): void
    {
        $category = Category::create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'description' => 'Books about practical Christian living.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Walking in Dominion',
            'slug' => 'walking-in-dominion',
            'subtitle' => 'Living From Your Kingdom Authority',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category->id);

        $response = $this->getJson(
            '/api/books/walking-in-dominion'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.categories.0.name',
                'Christian Living'
            )
            ->assertJsonPath(
                'data.categories.0.slug',
                'christian-living'
            );
    }

    /**
     * Books can be filtered by category slug.
     */
    public function test_books_can_be_filtered_by_category(): void
    {
        $dominion = Category::create([
            'name' => 'Dominion',
            'slug' => 'dominion',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $prosperity = Category::create([
            'name' => 'Prosperity',
            'slug' => 'prosperity',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $dominionBook = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $prosperityBook = Book::create([
            'title' => 'Kingdom Prosperity',
            'slug' => 'kingdom-prosperity',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $dominionBook->categories()->attach($dominion->id);
        $prosperityBook->categories()->attach($prosperity->id);

        $response = $this->getJson(
            '/api/books?category=dominion'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.title',
                'Born to Rule'
            )
            ->assertJsonPath(
                'data.0.categories.0.slug',
                'dominion'
            );
    }

    /**
     * A book may belong to multiple categories.
     */
    public function test_book_can_belong_to_multiple_categories(): void
    {
        $categoryOne = Category::create([
            'name' => 'Prayer',
            'slug' => 'prayer',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $categoryTwo = Category::create([
            'name' => 'Spiritual Warfare',
            'slug' => 'spiritual-warfare',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->sync([
            $categoryOne->id,
            $categoryTwo->id,
        ]);

        $this->assertCount(
            2,
            $book->fresh()->categories
        );

        $this->assertTrue(
            $book->fresh()
                ->categories
                ->contains($categoryOne)
        );

        $this->assertTrue(
            $book->fresh()
                ->categories
                ->contains($categoryTwo)
        );
    }

    /**
     * Inactive categories cannot be used to filter
     * the public book catalogue.
     */
    public function test_inactive_category_does_not_return_books_in_public_filter(): void
    {
        $category = Category::create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $book = Book::create([
            'title' => 'Hidden Category Book',
            'slug' => 'hidden-category-book',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category->id);

        $response = $this->getJson(
            '/api/books?category=hidden-category'
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Unpublished books must not appear
     * in category-filtered public results.
     */
    public function test_unpublished_books_are_not_returned_by_category_filter(): void
    {
        $category = Category::create([
            'name' => 'Leadership',
            'slug' => 'leadership',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'author' => 'William K. Danquah',
            'price' => 10.00,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => false,
            'published_at' => null,
        ]);

        $book->categories()->attach($category->id);

        $response = $this->getJson(
            '/api/books?category=leadership'
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}