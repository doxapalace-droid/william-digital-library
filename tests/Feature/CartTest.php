<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot view the cart.
     */
    public function test_guest_cannot_view_cart(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated users can view an empty cart.
     */
    public function test_user_can_view_empty_cart(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('subtotal', 0)
            ->assertJsonPath('total', 0);
    }

    /**
     * Authenticated users can add a published book to the cart.
     */
    public function test_user_can_add_book_to_cart(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Test Digital Book',
            'price' => 20.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id)
            ->assertJsonPath('data.book.title', 'Test Digital Book')
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.unit_price', '20.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.subtotal', '20.00');

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'quantity' => 1,
        ]);
    }

    /**
     * A book cannot be added to the cart twice.
     */
    public function test_user_cannot_add_same_book_twice(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ])->assertCreated();

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'book_uuid',
            ]);

        $this->assertDatabaseCount('cart_items', 1);
    }

    /**
     * An unpublished book cannot be added to the cart.
     */
    public function test_unpublished_book_cannot_be_added_to_cart(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'is_published' => false,
            'published_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'book_uuid',
            ]);

        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * A nonexistent book cannot be added to the cart.
     */
    public function test_nonexistent_book_cannot_be_added_to_cart(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'book_uuid',
            ]);
    }

    /**
     * A customer cannot add a book they already own.
     */
    public function test_customer_cannot_add_book_they_already_own(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'is_published' => true,
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
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'book_uuid',
            ]);

        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * An expired entitlement does not prevent the customer
     * from purchasing the book again.
     */
    public function test_expired_entitlement_does_not_block_cart(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id);

        $this->assertDatabaseCount('cart_items', 1);
    }

    /**
     * A revoked entitlement does not block the customer
     * from purchasing the book again.
     */
    public function test_revoked_entitlement_does_not_block_cart(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now()->subMonth(),
            'expires_at' => null,
            'revoked_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id);

        $this->assertDatabaseCount('cart_items', 1);
    }

    /**
     * Inactive entitlement does not block a new purchase.
     */
    public function test_inactive_entitlement_does_not_block_cart(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'inactive',
            'granted_at' => now()->subMonth(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.book.id', $book->id);
    }

    /**
     * The cart only contains items belonging to
     * the authenticated user.
     */
    public function test_user_only_sees_their_own_cart_items(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $bookOne = Book::factory()->create([
            'price' => 10.00,
            'is_published' => true,
        ]);

        $bookTwo = Book::factory()->create([
            'price' => 20.00,
            'is_published' => true,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $bookOne->id,
            'unit_price' => 10.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 10.00,
        ]);

        CartItem::create([
            'user_id' => $otherUser->id,
            'book_id' => $bookTwo->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.book.id', $bookOne->id);
    }

    /**
     * The cart response contains frontend-ready data.
     */
    public function test_cart_returns_frontend_ready_data(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Kingdom Authority',
            'author' => 'William K. Danquah',
            'cover_image' => 'covers/binding-and-loosing.jpg',
            'price' => 25.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'quantity',
                        'unit_price',
                        'currency',
                        'subtotal',
                        'book' => [
                            'id',
                            'uuid',
                            'title',
                            'slug',
                            'subtitle',
                            'author',
                            'cover_image',
                            'price',
                            'currency',
                        ],
                    ],
                ],
                'subtotal',
                'total',
            ])
            ->assertJsonPath(
                'data.0.book.title',
                'The Power of Binding and Loosing'
            )
            ->assertJsonPath(
                'data.0.book.author',
                'William K. Danquah'
            );
    }

    /**
     * Private book files must never be exposed through the cart.
     */
    public function test_cart_does_not_expose_private_book_files(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'ebook_file' => 'private/books/secret.epub',
            'pdf_path' => 'private/books/secret.pdf',
            'price' => 20.00,
            'is_published' => true,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.0.book.ebook_file')
            ->assertJsonMissingPath('data.0.book.pdf_path');
    }

    /**
     * Cart item price is captured when the item is added.
     */
    public function test_cart_item_keeps_captured_price(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 25.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'book_uuid' => $book->uuid,
        ])->assertCreated();

        $book->update([
            'price' => 40.00,
        ]);

        $cartItem = CartItem::first();

        $this->assertSame('25.00', $cartItem->unit_price);
        $this->assertSame('25.00', $cartItem->subtotal);
    }
}