<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest cannot view order history.
     */
    public function test_guest_cannot_view_orders(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated customer can view their order history.
     */
    public function test_user_can_view_their_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 30.00,
            'discount' => 0.00,
            'total' => 30.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.order_number',
                'DP-000001'
            )
            ->assertJsonPath(
                'data.0.status',
                'completed'
            )
            ->assertJsonPath(
                'data.0.payment_status',
                'paid'
            )
            ->assertJsonPath(
                'data.0.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.0.subtotal',
                '30.00'
            )
            ->assertJsonPath(
                'data.0.total',
                '30.00'
            );
    }

    /**
     * Customer cannot see another customer's orders.
     */
    public function test_user_only_sees_their_own_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000001',
        ]);

        Order::factory()->create([
            'user_id' => $otherUser->id,
            'order_number' => 'DP-000002',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.order_number',
                'DP-000001'
            )
            ->assertJsonMissing([
                'order_number' => 'DP-000002',
            ]);
    }

    /**
     * Empty order history returns an empty collection.
     */
    public function test_user_can_view_empty_order_history(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.total',
                0
            );
    }

    /**
     * Orders are paginated.
     */
    public function test_orders_are_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            Order::factory()->create([
                'user_id' => $user->id,
                'order_number' => sprintf(
                    'DP-%06d',
                    $i
                ),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                10
            )
            ->assertJsonPath(
                'meta.total',
                12
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            );
    }

    /**
     * Customer can view one of their orders.
     */
    public function test_user_can_view_their_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000100',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 40.00,
            'discount' => 5.00,
            'total' => 35.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $order->id
            )
            ->assertJsonPath(
                'data.uuid',
                $order->uuid
            )
            ->assertJsonPath(
                'data.order_number',
                'DP-000100'
            )
            ->assertJsonPath(
                'data.status',
                'completed'
            )
            ->assertJsonPath(
                'data.payment_status',
                'paid'
            )
            ->assertJsonPath(
                'data.subtotal',
                '40.00'
            )
            ->assertJsonPath(
                'data.discount',
                '5.00'
            )
            ->assertJsonPath(
                'data.total',
                '35.00'
            );
    }

    /**
     * Order details include purchased items.
     */
    public function test_order_details_include_items(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Kingdom Dominion',
            'slug' => 'kingdom-dominion',
            'subtitle' => 'Walking in Authority',
            'author' => 'William K. Danquah',
            'cover_image' => 'covers/kingdom-dominion.jpg',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000200',
            'currency' => 'USD',
            'subtotal' => 25.00,
            'discount' => 0.00,
            'total' => 25.00,
        ]);

        /*
         * OrderItemFactory does not exist in this project.
         * Create the order item directly.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.quantity',
                1
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '25.00'
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                '25.00'
            )
            ->assertJsonPath(
                'data.items.0.book.id',
                $book->id
            )
            ->assertJsonPath(
                'data.items.0.book.uuid',
                $book->uuid
            )
            ->assertJsonPath(
                'data.items.0.book.title',
                'Kingdom Dominion'
            )
            ->assertJsonPath(
                'data.items.0.book.slug',
                'kingdom-dominion'
            );
    }

    /**
     * Customer cannot view another customer's order.
     */
    public function test_user_cannot_view_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'order_number' => 'DP-000300',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Customer cannot view a nonexistent order.
     */
    public function test_nonexistent_order_returns_not_found(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/orders/00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    /**
     * Order history does not expose individual items.
     */
    public function test_order_history_does_not_include_items(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000400',
        ]);

        /*
         * Create the order item directly because
         * this project does not have OrderItemFactory.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'unit_price' => $book->price,
            'currency' => $book->currency,
            'quantity' => 1,
            'subtotal' => $book->price,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'items' => [],
            ]);

        $this->assertArrayNotHasKey(
            'items',
            $response->json('data.0')
        );
    }
}