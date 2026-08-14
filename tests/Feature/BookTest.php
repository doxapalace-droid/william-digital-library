<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin user for testing.
     */
    private function createAdmin(): User
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
            'status' => true,
        ]);

        return User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
    }

    /**
     * Create a customer user for testing.
     */
    private function createCustomer(): User
    {
        $customerRole = Role::create([
            'name' => 'Customer',
            'slug' => 'customer',
            'description' => 'Library customer',
            'is_system' => false,
            'status' => true,
        ]);

        return User::factory()->create([
            'role_id' => $customerRole->id,
        ]);
    }

    /**
     * Create a fake PDF upload.
     */
    private function fakePdf(): UploadedFile
    {
        return UploadedFile::fake()->create(
            'book.pdf',
            100,
            'application/pdf'
        );
    }

    /**
     * Base payload for creating a book.
     */
    private function bookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'The Power of Binding and Loosing',
            'subtitle' => 'Understanding Your Kingdom Authority',
            'description' => 'A book about exercising spiritual authority.',
            'price' => 6.99,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
        ], $overrides);
    }

    /**
     * A book can be created directly.
     */
    public function test_book_can_be_created(): void
    {
        $book = Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Your Kingdom Authority',
            'description' => 'A book about exercising spiritual authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertDatabaseHas('books', [
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'author' => 'William K. Danquah',
        ]);

        $this->assertNotNull($book->uuid);
        $this->assertTrue($book->is_featured);
        $this->assertTrue($book->is_published);
    }

    /**
     * Published books can be listed.
     */
    public function test_published_books_can_be_listed(): void
    {
        Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'description' => 'Understanding Kingdom authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.title',
                'The Power of Binding and Loosing'
            )
            ->assertJsonPath(
                'data.0.author',
                'William K. Danquah'
            );
    }

    /**
     * Unpublished books are not listed.
     */
    public function test_unpublished_books_are_not_listed(): void
    {
        Book::create([
            'title' => 'Published Book',
            'slug' => 'published-book',
            'description' => 'This book is available to customers.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Unpublished Book',
            'slug' => 'unpublished-book',
            'description' => 'This book is still being prepared.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => false,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/books');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Published Book',
            ])
            ->assertJsonMissing([
                'title' => 'Unpublished Book',
            ]);
    }

    /**
     * A published book can be viewed by its slug.
     */
    public function test_published_book_can_be_viewed_by_slug(): void
    {
        Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Your Kingdom Authority',
            'description' => 'A book about exercising spiritual authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/books/the-power-of-binding-and-loosing'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'The Power of Binding and Loosing'
            )
            ->assertJsonPath(
                'data.slug',
                'the-power-of-binding-and-loosing'
            )
            ->assertJsonPath(
                'data.author',
                'William K. Danquah'
            );
    }

    /**
     * An unpublished book cannot be viewed by its slug.
     */
    public function test_unpublished_book_cannot_be_viewed_by_slug(): void
    {
        Book::create([
            'title' => 'Secret Upcoming Book',
            'slug' => 'secret-upcoming-book',
            'description' => 'This book has not been published yet.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => false,
            'published_at' => null,
        ]);

        $response = $this->getJson(
            '/api/books/secret-upcoming-book'
        );

        $response->assertNotFound();
    }

    /**
     * A book can belong to an author.
     */
    public function test_book_can_belong_to_an_author(): void
    {
        $author = Author::factory()->create([
            'name' => 'William K. Danquah',
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $book->authors()->attach($author);

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $author->id)
                ->exists()
        );

        $this->assertDatabaseHas('author_book', [
            'author_id' => $author->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * An author can have multiple books.
     */
    public function test_author_can_have_multiple_books(): void
    {
        $author = Author::factory()->create();

        $bookOne = Book::factory()->create([
            'is_published' => true,
        ]);

        $bookTwo = Book::factory()->create([
            'is_published' => true,
        ]);

        $author->books()->attach([
            $bookOne->id,
            $bookTwo->id,
        ]);

        $this->assertCount(
            2,
            $author->books()->get()
        );

        $this->assertTrue(
            $author->books()
                ->where('books.id', $bookOne->id)
                ->exists()
        );

        $this->assertTrue(
            $author->books()
                ->where('books.id', $bookTwo->id)
                ->exists()
        );
    }

    /**
     * A book can have multiple authors.
     */
    public function test_book_can_have_multiple_authors(): void
    {
        $authorOne = Author::factory()->create([
            'name' => 'William K. Danquah',
        ]);

        $authorTwo = Author::factory()->create([
            'name' => 'John Doe',
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $this->assertCount(
            2,
            $book->authors()->get()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorOne->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorTwo->id)
                ->exists()
        );
    }

    /**
     * A book's author relationship can be synchronized.
     */
    public function test_book_author_relationship_can_be_synced(): void
    {
        $authorOne = Author::factory()->create();
        $authorTwo = Author::factory()->create();
        $authorThree = Author::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $book->authors()->sync([
            $authorTwo->id,
            $authorThree->id,
        ]);

        $this->assertFalse(
            $book->authors()
                ->where('authors.id', $authorOne->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorTwo->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorThree->id)
                ->exists()
        );

        $this->assertCount(
            2,
            $book->authors()->get()
        );
    }

    /**
     * A book's author relationship can be removed.
     */
    public function test_book_author_relationship_can_be_removed(): void
    {
        $authorOne = Author::factory()->create();
        $authorTwo = Author::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $book->authors()->detach($authorOne->id);

        $this->assertFalse(
            $book->authors()
                ->where('authors.id', $authorOne->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorTwo->id)
                ->exists()
        );

        $this->assertDatabaseMissing('author_book', [
            'author_id' => $authorOne->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseHas('author_book', [
            'author_id' => $authorTwo->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * A book can be assigned to a category.
     */
    public function test_book_can_be_assigned_to_a_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category);

        $this->assertTrue(
            $book->categories->contains($category)
        );
    }

    /**
     * A book can have multiple categories.
     */
    public function test_book_can_have_multiple_categories(): void
    {
        $categoryOne = Category::factory()->create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'is_active' => true,
        ]);

        $categoryTwo = Category::factory()->create([
            'name' => 'Leadership',
            'slug' => 'leadership',
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach([
            $categoryOne->id,
            $categoryTwo->id,
        ]);

        $this->assertCount(2, $book->categories);

        $this->assertTrue(
            $book->categories->contains($categoryOne)
        );

        $this->assertTrue(
            $book->categories->contains($categoryTwo)
        );
    }

    /**
     * Books can be filtered by category slug.
     */
    public function test_books_can_be_filtered_by_category_slug(): void
    {
        $category = Category::factory()->create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'is_active' => true,
        ]);

        $otherCategory = Category::factory()->create([
            'name' => 'Leadership',
            'slug' => 'leadership',
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'title' => 'Christian Living Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $otherBook = Book::factory()->create([
            'title' => 'Leadership Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category);
        $otherBook->categories()->attach($otherCategory);

        $response = $this->getJson(
            '/api/books?category=christian-living'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Christian Living Book',
            ])
            ->assertJsonMissing([
                'title' => 'Leadership Book',
            ]);
    }

    /**
     * Books belonging to inactive categories are not returned
     * when filtering by that category.
     */
    public function test_inactive_category_does_not_return_books(): void
    {
        $category = Category::factory()->create([
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $book = Book::factory()->create([
            'title' => 'Hidden Category Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category);

        $response = $this->getJson(
            '/api/books?category=inactive-category'
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'title' => 'Hidden Category Book',
            ]);
    }

    /**
     * The catalogue supports pagination.
     */
    public function test_books_can_be_paginated(): void
    {
        Book::factory()->count(5)->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    /**
     * The catalogue can search published books.
     */
    public function test_books_can_be_searched(): void
    {
        Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A book about kingdom authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'The Power of Unity',
            'slug' => 'the-power-of-unity',
            'description' => 'A book about unity.',
            'author' => 'William K. Danquah',
            'price' => 5.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?search=Born');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Born to Rule',
            ])
            ->assertJsonMissing([
                'title' => 'The Power of Unity',
            ]);
    }

    /**
     * The catalogue can filter books by featured status.
     */
    public function test_featured_books_can_be_filtered(): void
    {
        Book::create([
            'title' => 'Featured Book',
            'slug' => 'featured-book',
            'description' => 'A featured book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Regular Book',
            'slug' => 'regular-book',
            'description' => 'A regular book.',
            'author' => 'William K. Danquah',
            'price' => 5.99,
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?featured=1');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Featured Book',
            ])
            ->assertJsonMissing([
                'title' => 'Regular Book',
            ]);
    }

    /**
     * The catalogue can filter books by author slug.
     */
    public function test_books_can_be_filtered_by_author(): void
    {
        $author = Author::factory()->create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
        ]);

        $book = Book::factory()->create([
            'title' => 'William Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $otherBook = Book::factory()->create([
            'title' => 'Other Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author);

        $response = $this->getJson(
            '/api/books?author=william-k-danquah'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'William Book',
            ])
            ->assertJsonMissing([
                'title' => 'Other Book',
            ]);
    }

    /**
     * The catalogue can sort books by title ascending.
     */
    public function test_books_can_be_sorted_by_title_ascending(): void
    {
        Book::create([
            'title' => 'Zebra Book',
            'slug' => 'zebra-book',
            'description' => 'Zebra.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Alpha Book',
            'slug' => 'alpha-book',
            'description' => 'Alpha.',
            'author' => 'William K. Danquah',
            'price' => 5.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?sort=title_asc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Alpha Book')
            ->assertJsonPath('data.1.title', 'Zebra Book');
    }

    /**
     * The catalogue can sort books by title descending.
     */
    public function test_books_can_be_sorted_by_title_descending(): void
    {
        Book::create([
            'title' => 'Alpha Book',
            'slug' => 'alpha-book',
            'description' => 'Alpha.',
            'author' => 'William K. Danquah',
            'price' => 5.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Zebra Book',
            'slug' => 'zebra-book',
            'description' => 'Zebra.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?sort=title_desc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Zebra Book')
            ->assertJsonPath('data.1.title', 'Alpha Book');
    }

    /**
     * The catalogue can sort books by price ascending.
     */
    public function test_books_can_be_sorted_by_price_ascending(): void
    {
        Book::create([
            'title' => 'Expensive Book',
            'slug' => 'expensive-book',
            'description' => 'Expensive.',
            'author' => 'William K. Danquah',
            'price' => 12.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Cheap Book',
            'slug' => 'cheap-book',
            'description' => 'Cheap.',
            'author' => 'William K. Danquah',
            'price' => 3.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?sort=price_asc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Cheap Book')
            ->assertJsonPath('data.1.title', 'Expensive Book');
    }

    /**
     * The catalogue can sort books by price descending.
     */
    public function test_books_can_be_sorted_by_price_descending(): void
    {
        Book::create([
            'title' => 'Cheap Book',
            'slug' => 'cheap-book',
            'description' => 'Cheap.',
            'author' => 'William K. Danquah',
            'price' => 3.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Expensive Book',
            'slug' => 'expensive-book',
            'description' => 'Expensive.',
            'author' => 'William K. Danquah',
            'price' => 12.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books?sort=price_desc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Expensive Book')
            ->assertJsonPath('data.1.title', 'Cheap Book');
    }

    /**
     * The dedicated book search endpoint can search by title.
     */
    public function test_dedicated_book_search_can_search_by_title(): void
    {
        Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'Kingdom authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'The Power of Unity',
            'slug' => 'the-power-of-unity',
            'description' => 'Unity.',
            'author' => 'William K. Danquah',
            'price' => 5.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books/search?q=Born');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Born to Rule',
            ])
            ->assertJsonMissing([
                'title' => 'The Power of Unity',
            ]);
    }

    /**
     * The dedicated book search is case-insensitive.
     */
    public function test_dedicated_book_search_is_case_insensitive(): void
    {
        Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'Kingdom authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/books/search?q=born');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Born to Rule',
            ]);
    }

    /**
     * The dedicated book search never exposes unpublished books.
     */
    public function test_dedicated_book_search_excludes_unpublished_books(): void
    {
        Book::create([
            'title' => 'Published Dominion Book',
            'slug' => 'published-dominion-book',
            'description' => 'Dominion.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Book::create([
            'title' => 'Unpublished Dominion Book',
            'slug' => 'unpublished-dominion-book',
            'description' => 'Dominion.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => false,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/books/search?q=Dominion');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Published Dominion Book',
            ])
            ->assertJsonMissing([
                'title' => 'Unpublished Dominion Book',
            ]);
    }

    /**
     * An invalid catalogue sort value is rejected.
     */
    public function test_invalid_catalogue_sort_is_rejected(): void
    {
        $response = $this->getJson('/api/books?sort=invalid');

        $response->assertStatus(422);
    }

    /**
     * An invalid pagination size is rejected.
     */
    public function test_invalid_catalogue_per_page_is_rejected(): void
    {
        $response = $this->getJson('/api/books?per_page=100');

        $response->assertStatus(422);
    }

    /**
     * Admin can create a book with multiple authors.
     */
    public function test_admin_can_create_book_with_multiple_authors(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $authorOne = Author::factory()->create([
            'name' => 'William K. Danquah',
            'is_active' => true,
        ]);

        $authorTwo = Author::factory()->create([
            'name' => 'John Doe',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                ...$this->bookPayload(),
                'authors' => [
                    $authorOne->id,
                    $authorTwo->id,
                ],
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Book created successfully.'
            );

        $book = Book::where(
            'title',
            'The Power of Binding and Loosing'
        )->firstOrFail();

        $this->assertCount(2, $book->authors);

        $this->assertTrue(
            $book->authors->contains($authorOne)
        );

        $this->assertTrue(
            $book->authors->contains($authorTwo)
        );

        $this->assertNotNull($book->pdf_path);

        Storage::disk('books')->assertExists(
            $book->pdf_path
        );
    }

    /**
     * Admin can create a book with multiple categories.
     */
    public function test_admin_can_create_book_with_multiple_categories(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $categoryOne = Category::factory()->create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'is_active' => true,
        ]);

        $categoryTwo = Category::factory()->create([
            'name' => 'Leadership',
            'slug' => 'leadership',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                ...$this->bookPayload([
                    'title' => 'Kingdom Leadership',
                ]),
                'categories' => [
                    $categoryOne->id,
                    $categoryTwo->id,
                ],
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated();

        $book = Book::where(
            'title',
            'Kingdom Leadership'
        )->firstOrFail();

        $this->assertCount(2, $book->categories);

        $this->assertTrue(
            $book->categories->contains($categoryOne)
        );

        $this->assertTrue(
            $book->categories->contains($categoryTwo)
        );
    }

    /**
     * Admin can create a book with authors and categories.
     */
    public function test_admin_can_create_book_with_authors_and_categories(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $authorOne = Author::factory()->create([
            'name' => 'William K. Danquah',
            'is_active' => true,
        ]);

        $authorTwo = Author::factory()->create([
            'name' => 'John Doe',
            'is_active' => true,
        ]);

        $categoryOne = Category::factory()->create([
            'name' => 'Prayer',
            'slug' => 'prayer',
            'is_active' => true,
        ]);

        $categoryTwo = Category::factory()->create([
            'name' => 'Spiritual Growth',
            'slug' => 'spiritual-growth',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                ...$this->bookPayload([
                    'title' => 'Prayer and Spiritual Growth',
                ]),
                'authors' => [
                    $authorOne->id,
                    $authorTwo->id,
                ],
                'categories' => [
                    $categoryOne->id,
                    $categoryTwo->id,
                ],
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertCreated();

        $book = Book::where(
            'title',
            'Prayer and Spiritual Growth'
        )->firstOrFail();

        $this->assertCount(2, $book->authors);
        $this->assertCount(2, $book->categories);
    }

    /**
     * Admin can update a book's authors.
     */
    public function test_admin_can_update_book_authors(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $authorOne = Author::factory()->create([
            'name' => 'William K. Danquah',
            'is_active' => true,
        ]);

        $authorTwo = Author::factory()->create([
            'name' => 'John Doe',
            'is_active' => true,
        ]);

        $authorThree = Author::factory()->create([
            'name' => 'Jane Doe',
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'title' => 'Author Update Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'authors' => [
                        $authorTwo->id,
                        $authorThree->id,
                    ],
                ]
            );

        $response->assertOk();

        $book->refresh();

        $this->assertFalse(
            $book->authors()
                ->where('authors.id', $authorOne->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorTwo->id)
                ->exists()
        );

        $this->assertTrue(
            $book->authors()
                ->where('authors.id', $authorThree->id)
                ->exists()
        );
    }

    /**
     * Admin can update a book's categories.
     */
    public function test_admin_can_update_book_categories(): void
    {
        $admin = $this->createAdmin();

        $categoryOne = Category::factory()->create([
            'is_active' => true,
        ]);

        $categoryTwo = Category::factory()->create([
            'is_active' => true,
        ]);

        $categoryThree = Category::factory()->create([
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach([
            $categoryOne->id,
            $categoryTwo->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'categories' => [
                        $categoryTwo->id,
                        $categoryThree->id,
                    ],
                ]
            );

        $response->assertOk();

        $book->refresh();

        $this->assertFalse(
            $book->categories()
                ->where('categories.id', $categoryOne->id)
                ->exists()
        );

        $this->assertTrue(
            $book->categories()
                ->where('categories.id', $categoryTwo->id)
                ->exists()
        );

        $this->assertTrue(
            $book->categories()
                ->where('categories.id', $categoryThree->id)
                ->exists()
        );
    }

    /**
     * Sending authors as an empty array removes all authors.
     */
    public function test_empty_authors_array_removes_all_authors(): void
    {
        $admin = $this->createAdmin();

        $authorOne = Author::factory()->create([
            'is_active' => true,
        ]);

        $authorTwo = Author::factory()->create([
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'authors' => [],
                ]
            );

        $response->assertOk();

        $this->assertCount(
            0,
            $book->fresh()->authors
        );
    }

    /**
     * Sending categories as an empty array removes all categories.
     */
    public function test_empty_categories_array_removes_all_categories(): void
    {
        $admin = $this->createAdmin();

        $categoryOne = Category::factory()->create([
            'is_active' => true,
        ]);

        $categoryTwo = Category::factory()->create([
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach([
            $categoryOne->id,
            $categoryTwo->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'categories' => [],
                ]
            );

        $response->assertOk();

        $this->assertCount(
            0,
            $book->fresh()->categories
        );
    }

    /**
     * Omitting authors during update leaves existing authors unchanged.
     */
    public function test_omitting_authors_leaves_existing_authors_unchanged(): void
    {
        $admin = $this->createAdmin();

        $author = Author::factory()->create([
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'title' => 'Updated Book Title',
                ]
            );

        $response->assertOk();

        $this->assertTrue(
            $book->fresh()
                ->authors()
                ->where('authors.id', $author->id)
                ->exists()
        );
    }

    /**
     * Omitting categories during update leaves existing categories unchanged.
     */
    public function test_omitting_categories_leaves_existing_categories_unchanged(): void
    {
        $admin = $this->createAdmin();

        $category = Category::factory()->create([
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->categories()->attach($category);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'title' => 'Updated Book Title',
                ]
            );

        $response->assertOk();

        $this->assertTrue(
            $book->fresh()
                ->categories()
                ->where('categories.id', $category->id)
                ->exists()
        );
    }

    /**
     * Inactive authors cannot be assigned to a book.
     */
    public function test_inactive_author_cannot_be_assigned_to_book(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $inactiveAuthor = Author::factory()->create([
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                ...$this->bookPayload([
                    'title' => 'Inactive Author Book',
                ]),
                'authors' => [
                    $inactiveAuthor->id,
                ],
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'authors.0',
            ]);
    }

    /**
     * Inactive categories cannot be assigned to a book.
     */
    public function test_inactive_category_cannot_be_assigned_to_book(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $inactiveCategory = Category::factory()->create([
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                ...$this->bookPayload([
                    'title' => 'Inactive Category Book',
                ]),
                'categories' => [
                    $inactiveCategory->id,
                ],
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'categories.0',
            ]);
    }

    /**
     * Customer cannot create a book.
     */
    public function test_customer_cannot_create_a_book(): void
    {
        Storage::fake('books');

        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer)
            ->post('/api/admin/books', [
                ...$this->bookPayload([
                    'title' => 'Unauthorized Book',
                ]),
                'pdf' => $this->fakePdf(),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('books', [
            'title' => 'Unauthorized Book',
        ]);
    }

    /**
     * Guest cannot create a book.
     */
    public function test_guest_cannot_create_a_book(): void
    {
        Storage::fake('books');

        $response = $this->post('/api/admin/books', [
            ...$this->bookPayload([
                'title' => 'Guest Book',
            ]),
            'pdf' => $this->fakePdf(),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('books', [
            'title' => 'Guest Book',
        ]);
    }

    /**
     * Related authors are returned in the book catalogue.
     */
    public function test_book_catalogue_returns_related_authors(): void
    {
        $author = Author::factory()->create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
        ]);

        $book = Book::factory()->create([
            'title' => 'Born to Rule',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author);

        $response = $this->getJson('/api/books');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Born to Rule',
            ])
            ->assertJsonFragment([
                'name' => 'William K. Danquah',
            ]);
    }

    /**
     * A book can be found through its related author.
     */
    public function test_catalogue_search_can_find_book_by_related_author(): void
    {
        $author = Author::factory()->create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
        ]);

        $book = Book::factory()->create([
            'title' => 'Kingdom Authority',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $otherBook = Book::factory()->create([
            'title' => 'Another Book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author);

        $response = $this->getJson(
            '/api/books?search=William'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Kingdom Authority',
            ])
            ->assertJsonMissing([
                'title' => 'Another Book',
            ]);
    }
}