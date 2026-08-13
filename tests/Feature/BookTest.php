<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A book can be created.
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
}