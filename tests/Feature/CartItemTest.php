<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A cart item belongs to a user.
     */
    public function test_cart_item_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create();

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $this->assertTrue(
            $cartItem->user->is($user)
        );
    }

    /**
     * A cart item belongs to a book.
     */
    public function test_cart_item_belongs_to_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Test Book',
        ]);

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $this->assertTrue(
            $cartItem->book->is($book)
        );
    }

    /**
     * A user can have multiple cart items.
     */
    public function test_user_can_have_multiple_cart_items(): void
    {
        $user = User::factory()->create();

        $bookOne = Book::factory()->create();
        $bookTwo = Book::factory()->create();

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

        $this->assertCount(2, $user->cartItems);
    }

    /**
     * A book can appear in multiple users' carts.
     */
    public function test_book_can_belong_to_multiple_cart_items(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $book = Book::factory()->create();

        CartItem::create([
            'user_id' => $userOne->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        CartItem::create([
            'user_id' => $userTwo->id,
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $this->assertCount(2, $book->cartItems);
    }

    /**
     * Cart item monetary values are cast correctly.
     */
    public function test_cart_item_values_are_cast_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 25.50,
            'currency' => 'USD',
            'quantity' => 2,
            'subtotal' => 51.00,
        ]);

        $this->assertSame('25.50', $cartItem->unit_price);
        $this->assertSame(2, $cartItem->quantity);
        $this->assertSame('51.00', $cartItem->subtotal);
    }

    /**
     * Cart item subtotal can be calculated from price and quantity.
     */
    public function test_cart_item_can_calculate_subtotal(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'unit_price' => 15.50,
            'currency' => 'USD',
            'quantity' => 3,
            'subtotal' => 46.50,
        ]);

        $this->assertSame(
            46.50,
            $cartItem->calculateSubtotal()
        );
    }
}