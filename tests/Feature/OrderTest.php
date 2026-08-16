<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An order belongs to a customer.
     */
    public function test_order_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        $this->assertTrue(
            $order->user->is($user)
        );
    }

    /**
     * An order can contain order items.
     */
    public function test_order_has_order_items(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000002',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'book',
            'book_id' => $book->id,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        $this->assertTrue(
            $order->items->contains($item)
        );
    }

    /**
     * An order item belongs to its order.
     */
    public function test_order_item_belongs_to_order(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000003',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 15.00,
            'discount' => 0,
            'total' => 15.00,
        ]);

        $book = Book::factory()->create();

        $item = OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'book',
            'book_id' => $book->id,
            'unit_price' => 15.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 15.00,
        ]);

        $this->assertTrue(
            $item->order->is($order)
        );
    }

    /**
     * An order item belongs to its book.
     */
    public function test_order_item_belongs_to_book(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000004',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 25.00,
            'discount' => 0,
            'total' => 25.00,
        ]);

        $book = Book::factory()->create([
            'price' => 25.00,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'book',
            'book_id' => $book->id,
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        $this->assertTrue(
            $item->book->is($book)
        );
    }

    /**
     * Order payment state is correctly identified.
     */
    public function test_order_payment_state(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000005',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 30.00,
            'discount' => 0,
            'total' => 30.00,
            'paid_at' => now(),
        ]);

        $this->assertTrue($order->isPaid());
        $this->assertTrue($order->isCompleted());
        $this->assertFalse($order->canBePaid());
    }

    /**
     * An unpaid pending order can be paid.
     */
    public function test_unpaid_order_can_be_paid(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000006',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 10.00,
            'discount' => 0,
            'total' => 10.00,
        ]);

        $this->assertTrue($order->canBePaid());
    }
}