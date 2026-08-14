<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookReaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A customer without an entitlement cannot open the book reader.
     */
    public function test_customer_without_entitlement_cannot_open_book_reader(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Protected Book',
            'slug' => 'protected-book',
            'description' => 'A protected digital book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not have access to this book.',
            ]);
    }

    /**
     * A customer with an active entitlement can read a private PDF.
     */
    public function test_customer_with_entitlement_can_read_book(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $pdfPath = 'born-to-rule.pdf';

        Storage::disk('books')->put(
            $pdfPath,
            '%PDF-1.4 test pdf content'
        );

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'Kingdom authority.',
            'author' => 'William K. Danquah',
            'pdf_path' => $pdfPath,
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'inline; filename="born-to-rule.pdf"'
            );

        $this->assertStringContainsString(
            '%PDF-1.4 test pdf content',
            $response->streamedContent()
        );

        Storage::disk('books')->assertExists($pdfPath);
    }

    /**
     * Reading does not require download permission.
     */
    public function test_customer_can_read_book_without_download_permission(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $pdfPath = 'protected-reading-book.pdf';

        Storage::disk('books')->put(
            $pdfPath,
            '%PDF-1.4 protected reading content'
        );

        $book = Book::create([
            'title' => 'Protected Reading Book',
            'slug' => 'protected-reading-book',
            'description' => 'A protected digital book.',
            'author' => 'William K. Danquah',
            'pdf_path' => $pdfPath,
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString(
            '%PDF-1.4 protected reading content',
            $response->streamedContent()
        );
    }

    /**
     * A customer with an expired entitlement cannot open the book reader.
     */
    public function test_customer_with_expired_entitlement_cannot_open_book_reader(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Expired Access Book',
            'slug' => 'expired-access-book',
            'description' => 'A protected digital book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not have access to this book.',
            ]);
    }

    /**
     * An authorised customer cannot read a book with no PDF path.
     */
    public function test_authorised_customer_cannot_read_book_without_pdf_path(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Missing PDF Book',
            'slug' => 'missing-pdf-book',
            'description' => 'A book without a PDF.',
            'author' => 'William K. Danquah',
            'pdf_path' => null,
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Book file not found.',
            ]);
    }

    /**
     * An authorised customer cannot read a book whose PDF is missing.
     */
    public function test_authorised_customer_cannot_read_when_pdf_file_does_not_exist(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $pdfPath = 'missing-file.pdf';

        $book = Book::create([
            'title' => 'Missing File Book',
            'slug' => 'missing-file-book',
            'description' => 'A book whose PDF is missing.',
            'author' => 'William K. Danquah',
            'pdf_path' => $pdfPath,
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->uuid}/read");

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Book file not found.',
            ]);
    }
}