<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookManagementTest extends TestCase
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
     * Admin can create a book and upload its PDF.
     */
    public function test_admin_can_create_a_book(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $pdf = UploadedFile::fake()->create(
            'born-to-rule.pdf',
            1000,
            'application/pdf'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                'title' => 'Born to Rule',
                'subtitle' => 'Unlocking Your Dominion in the Spirit Realm',
                'description' => 'A book about spiritual authority and dominion.',
                'author' => 'William K. Danquah',
                'price' => 6.99,
                'currency' => 'USD',
                'is_featured' => true,
                'is_published' => true,
                'pdf' => $pdf,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Book created successfully.')
            ->assertJsonPath('data.title', 'Born to Rule')
            ->assertJsonPath('data.author', 'William K. Danquah');

        $this->assertDatabaseHas('books', [
            'title' => 'Born to Rule',
            'author' => 'William K. Danquah',
            'slug' => 'born-to-rule',
        ]);

        $book = Book::where('title', 'Born to Rule')
            ->firstOrFail();

        $this->assertNotNull($book->pdf_path);

        Storage::disk('books')->assertExists(
            $book->pdf_path
        );
    }

    /**
     * Customer cannot create a book.
     */
    public function test_customer_cannot_create_a_book(): void
    {
        Storage::fake('books');

        $customer = $this->createCustomer();

        $pdf = UploadedFile::fake()->create(
            'unauthorized-book.pdf',
            1000,
            'application/pdf'
        );

        $response = $this
            ->actingAs($customer)
            ->post('/api/admin/books', [
                'title' => 'Unauthorized Book',
                'description' => 'This book should never be created.',
                'author' => 'Unauthorized User',
                'price' => 6.99,
                'currency' => 'USD',
                'is_published' => true,
                'pdf' => $pdf,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('books', [
            'title' => 'Unauthorized Book',
        ]);
    }

    /**
     * Admin can update book information.
     */
    public function test_admin_can_update_a_book(): void
    {
        $admin = $this->createAdmin();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'Original description.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/books/{$book->uuid}",
                [
                    'title' => 'Born to Rule Revised',
                    'description' => 'Updated description.',
                    'author' => 'William K. Danquah',
                    'price' => 7.99,
                    'currency' => 'USD',
                    'is_published' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Born to Rule Revised'
            )
            ->assertJsonPath(
                'data.slug',
                'born-to-rule-revised'
            );

        $this->assertDatabaseHas('books', [
            'uuid' => $book->uuid,
            'title' => 'Born to Rule Revised',
            'slug' => 'born-to-rule-revised',
        ]);
    }

    /**
     * Admin can replace an existing PDF.
     */
    public function test_admin_can_replace_a_book_pdf(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $oldPdfPath = 'old-book.pdf';

        Storage::disk('books')->put(
            $oldPdfPath,
            'old pdf contents'
        );

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'Original description.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
            'pdf_path' => $oldPdfPath,
        ]);

        Storage::disk('books')->assertExists(
            $oldPdfPath
        );

        $newPdf = UploadedFile::fake()->create(
            'born-to-rule-revised.pdf',
            1000,
            'application/pdf'
        );

        $response = $this
            ->actingAs($admin)
            ->post(
                "/api/admin/books/{$book->uuid}",
                [
                    '_method' => 'PUT',
                    'title' => 'Born to Rule Revised',
                    'pdf' => $newPdf,
                ]
            );

        $response->assertOk();

        $book->refresh();

        $this->assertNotNull($book->pdf_path);

        $this->assertNotEquals(
            $oldPdfPath,
            $book->pdf_path
        );

        Storage::disk('books')->assertExists(
            $book->pdf_path
        );

        Storage::disk('books')->assertMissing(
            $oldPdfPath
        );
    }

    /**
     * Admin can soft delete a book.
     */
    public function test_admin_can_delete_a_book(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $pdfPath = 'book-to-delete.pdf';

        Storage::disk('books')->put(
            $pdfPath,
            'pdf contents'
        );

        $book = Book::create([
            'title' => 'Book To Delete',
            'slug' => 'book-to-delete',
            'description' => 'This book will be soft deleted.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
            'pdf_path' => $pdfPath,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(
                "/api/admin/books/{$book->uuid}"
            );

        $response->assertNoContent();

        $this->assertSoftDeleted('books', [
            'uuid' => $book->uuid,
        ]);

        /*
         * Important:
         * A soft delete must NOT remove the PDF.
         */
        Storage::disk('books')->assertExists(
            $pdfPath
        );
    }

    /**
     * Customer cannot delete a book.
     */
    public function test_customer_cannot_delete_a_book(): void
    {
        $customer = $this->createCustomer();

        $book = Book::create([
            'title' => 'Protected Book',
            'slug' => 'protected-book',
            'description' => 'Customers must not be able to delete this book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer)
            ->deleteJson(
                "/api/admin/books/{$book->uuid}"
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('books', [
            'uuid' => $book->uuid,
            'title' => 'Protected Book',
            'deleted_at' => null,
        ]);
    }

    /**
     * Non-PDF files cannot be uploaded as books.
     */
    public function test_admin_cannot_upload_non_pdf_file(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $invalidFile = UploadedFile::fake()->create(
            'not-a-book.txt',
            100,
            'text/plain'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/api/admin/books', [
                'title' => 'Invalid Book',
                'author' => 'William K. Danquah',
                'price' => 6.99,
                'currency' => 'USD',
                'pdf' => $invalidFile,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pdf',
            ]);

        $this->assertDatabaseMissing('books', [
            'title' => 'Invalid Book',
        ]);
    }

    /**
     * PDF is required when creating a book.
     */
    public function test_pdf_is_required_when_creating_a_book(): void
    {
        Storage::fake('books');

        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/books', [
                'title' => 'Book Without PDF',
                'author' => 'William K. Danquah',
                'price' => 6.99,
                'currency' => 'USD',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pdf',
            ]);

        $this->assertDatabaseMissing('books', [
            'title' => 'Book Without PDF',
        ]);
    }
}