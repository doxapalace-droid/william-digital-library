<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyLibraryResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The library returns the information needed
     * by the customer-facing frontend.
     */
    public function test_library_returns_frontend_ready_book_data(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Kingdom Authority',
            'author' => 'William K. Danquah',
            'cover_image' => 'covers/binding-and-loosing.jpg',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Spiritual Growth',
            'slug' => 'spiritual-growth',
            'description' => 'Books for spiritual growth.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $book->categories()->attach($category);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.title', 'The Power of Binding and Loosing')
            ->assertJsonPath('data.0.slug', 'the-power-of-binding-and-loosing')
            ->assertJsonPath('data.0.subtitle', 'Understanding Kingdom Authority')
            ->assertJsonPath('data.0.author', 'William K. Danquah')
            ->assertJsonPath('data.0.categories.0.name', 'Spiritual Growth')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'title',
                        'slug',
                        'subtitle',
                        'description',
                        'author',
                        'cover_image',
                        'categories' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Private book files must never be exposed
     * through the library JSON response.
     */
    public function test_library_does_not_expose_private_book_file_paths(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'ebook_file' => 'private/books/secret.epub',
            'pdf_path' => 'private/books/secret.pdf',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.0.ebook_file')
            ->assertJsonMissingPath('data.0.pdf_path');
    }

    /**
     * Entitlement database details should not be
     * exposed directly to the customer.
     */
    public function test_library_does_not_expose_internal_entitlement_records(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.0.entitlements');
    }

    /**
 * Unpublished books must not appear in the customer's library,
 * even when the customer has a valid entitlement.
 */
    public function test_unpublished_book_does_not_appear_in_library(): void
    {
    $user = User::factory()->create();

    $book = Book::factory()->create([
        'title' => 'Unpublished Book',
        'is_published' => false,
        'published_at' => null,
    ]);

    BookEntitlement::create([
        'user_id' => $user->id,
        'book_id' => $book->id,
        'source' => 'purchase',
        'expires_at' => null,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/my-library');

    $response
        ->assertOk()
        ->assertJsonMissing([
            'title' => 'Unpublished Book',
        ]);
    }

    /**
 * Soft-deleted books must not appear in the customer's library.
 */
    public function test_soft_deleted_book_does_not_appear_in_library(): void   
    {
    $user = User::factory()->create();

    $book = Book::factory()->create([
        'title' => 'Withdrawn Book',
        'is_published' => true,
        'published_at' => now(),
    ]);

    BookEntitlement::create([
        'user_id' => $user->id,
        'book_id' => $book->id,
        'source' => 'purchase',
        'expires_at' => null,
    ]);

    $book->delete();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/my-library');

    $response
        ->assertOk()
        ->assertJsonMissing([
            'title' => 'Withdrawn Book',
        ]);
    }
}