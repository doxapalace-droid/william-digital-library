<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;


    /**
     * Guests cannot view checkout.
     */
    public function test_guest_cannot_view_checkout(): void
    {
        $response = $this->getJson('/api/checkout');

        $response->assertUnauthorized();
    }


    /**
     * Guests cannot create checkout orders.
     */
    public function test_guest_cannot_create_checkout_order(): void
    {
        $response = $this->postJson('/api/checkout');

        $response->assertUnauthorized();
    }


    /**
     * Authenticated user cannot checkout with an empty cart.
     */
    public function test_user_cannot_checkout_with_empty_cart(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }


    /**
     * Authenticated user can view checkout summary.
     */
    public function test_user_can_view_checkout_summary(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->getJson('/api/checkout');

        $response
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.total', '20.00');

        $this->assertCount(
            1,
            $response->json('data.items')
        );
    }


    /**
     * User can create a pending order from their cart.
     */
    public function test_user_can_create_checkout_order(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.order_number',
                'DP-000001'
            )
            ->assertJsonPath(
                'data.status',
                'pending'
            )
            ->assertJsonPath(
                'data.payment_status',
                'unpaid'
            )
            ->assertJsonPath(
                'data.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.subtotal',
                '20.00'
            )
            ->assertJsonPath(
                'data.discount',
                '0.00'
            )
            ->assertJsonPath(
                'data.total',
                '20.00'
            );

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $order = Order::first();

        $this->assertNotNull($order);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);
    }


    /**
     * Checkout preserves the price captured in the cart.
     *
     * The current book price may have changed after
     * the book was added to the cart.
     */
    public function test_checkout_uses_captured_cart_price(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 40.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.total', '20.00');

        $this->assertDatabaseHas('order_items', [
            'unit_price' => 20.00,
            'subtotal' => 20.00,
        ]);
    }


    /**
     * Unpublished books cannot be checked out.
     */
    public function test_unpublished_book_cannot_be_checked_out(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => false,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }


    /**
     * A customer cannot checkout a book they already own.
     */
    public function test_owned_book_cannot_be_checked_out(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }


    /**
     * Expired entitlement does not prevent checkout.
     */
    public function test_expired_entitlement_does_not_block_checkout(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'revoked_at' => null,
            'expires_at' => now()->subDay(),
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }


    /**
     * Revoked entitlement does not prevent checkout.
     */
    public function test_revoked_entitlement_does_not_block_checkout(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'revoked_at' => now(),
            'expires_at' => null,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }


    /**
     * Inactive entitlement does not prevent checkout.
     */
    public function test_inactive_entitlement_does_not_block_checkout(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'inactive',
            'can_read' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response->assertCreated();

        $this->assertDatabaseCount('orders', 1);
    }


    /**
     * User can only checkout their own cart.
     */
    public function test_user_can_only_checkout_their_own_cart(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cart',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }


    /**
     * Multiple cart items are converted into multiple order items.
     */
    public function test_multiple_cart_items_create_multiple_order_items(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $bookOne = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        $bookTwo = Book::factory()->create([
            'is_published' => true,
            'price' => 30.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $bookOne->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $bookTwo->id,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '50.00')
            ->assertJsonPath('data.total', '50.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
    }


    /**
     * Checkout does not mark the order as paid.
     */
    public function test_checkout_does_not_mark_order_as_paid(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }
}