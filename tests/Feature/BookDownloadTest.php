<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookDownloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customer without entitlement cannot download a book.
     */
    public function test_customer_without_entitlement_cannot_download_book(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'pdf_path' => 'born-to-rule.pdf',
        ]);

        Storage::disk('books')->put(
            'born-to-rule.pdf',
            'Fake PDF content'
        );

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->id}/download");

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have access to this book.',
            ]);
    }

    /**
     * Customer with read access but without download permission
     * cannot download the book.
     */
    public function test_customer_without_download_permission_cannot_download_book(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'pdf_path' => 'born-to-rule.pdf',
        ]);

        Storage::disk('books')->put(
            'born-to-rule.pdf',
            'Fake PDF content'
        );

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
            ->get("/api/books/{$book->id}/download");

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'Download is not permitted.',
            ]);
    }

    /**
     * Customer with an active entitlement and download permission
     * can download the book.
     */
    public function test_customer_with_download_permission_can_download_book(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'pdf_path' => 'born-to-rule.pdf',
        ]);

        Storage::disk('books')->put(
            'born-to-rule.pdf',
            'Fake PDF content'
        );

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->id}/download");

        $response
            ->assertOk()
            ->assertDownload('born-to-rule.pdf');
    }

    /**
     * Customer with an expired entitlement cannot download the book.
     */
    public function test_customer_with_expired_entitlement_cannot_download_book(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'pdf_path' => 'born-to-rule.pdf',
        ]);

        Storage::disk('books')->put(
            'born-to-rule.pdf',
            'Fake PDF content'
        );

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->id}/download");

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'You do not have access to this book.',
            ]);
    }

    /**
     * Authorized customer receives 404 when the PDF file is missing.
     */
    public function test_authorized_customer_cannot_download_missing_pdf_file(): void
    {
        Storage::fake('books');

        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A test book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'pdf_path' => 'missing-book.pdf',
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get("/api/books/{$book->id}/download");

        $response
            ->assertNotFound()
            ->assertJson([
                'message' => 'Book file not found.',
            ]);
    }
}