<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Basic Book Tests
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | Author Relationship Tests
    |--------------------------------------------------------------------------
    */

    public function test_book_can_belong_to_an_author(): void
    {
        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and minister.',
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A book about dominion.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author->id);

        $book->load('authors');

        $this->assertTrue(
            $book->authors->contains($author)
        );

        $this->assertDatabaseHas('author_book', [
            'author_id' => $author->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_author_can_have_multiple_books(): void
    {
        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and minister.',
            'is_active' => true,
        ]);

        $bookOne = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $bookTwo = Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $author->books()->attach([
            $bookOne->id,
            $bookTwo->id,
        ]);

        $author->load('books');

        $this->assertCount(2, $author->books);

        $this->assertTrue(
            $author->books->contains($bookOne)
        );

        $this->assertTrue(
            $author->books->contains($bookTwo)
        );
    }

    public function test_book_can_have_multiple_authors(): void
    {
        $authorOne = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and minister.',
            'is_active' => true,
        ]);

        $authorTwo = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Co-author.',
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'author' => 'William K. Danquah',
            'price' => 9.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach([
            $authorOne->id,
            $authorTwo->id,
        ]);

        $book->load('authors');

        $this->assertCount(2, $book->authors);

        $this->assertTrue(
            $book->authors->contains($authorOne)
        );

        $this->assertTrue(
            $book->authors->contains($authorTwo)
        );
    }

    public function test_book_author_relationship_can_be_synced(): void
    {
        $authorOne = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and minister.',
            'is_active' => true,
        ]);

        $authorTwo = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Co-author.',
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'author' => 'William K. Danquah',
            'price' => 9.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($authorOne->id);

        $book->authors()->sync([
            $authorTwo->id,
        ]);

        $book->load('authors');

        $this->assertCount(1, $book->authors);

        $this->assertTrue(
            $book->authors->contains($authorTwo)
        );

        $this->assertFalse(
            $book->authors->contains($authorOne)
        );
    }

    public function test_book_author_relationship_can_be_removed(): void
    {
        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and minister.',
            'is_active' => true,
        ]);

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A book about dominion.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $book->authors()->attach($author->id);

        $book->authors()->detach($author->id);

        $this->assertDatabaseMissing('author_book', [
            'author_id' => $author->id,
            'book_id' => $book->id,
        ]);
    }
}