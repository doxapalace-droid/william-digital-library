<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
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
     * Order details include a purchased book.
     */
    public function test_order_details_include_book_item(): void
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

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => null,
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
                'data.items.0.item_type',
                'book'
            )
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
            )
            ->assertJsonPath(
                'data.items.0.audiobook',
                null
            );
    }

    /**
     * Order details include an audiobook.
     */
    public function test_order_details_include_audiobook_item(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create();

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'description' => 'Kingdom Dominion audiobook',
            'price' => 15.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000201',
            'currency' => 'USD',
            'subtotal' => 15.00,
            'discount' => 0.00,
            'total' => 15.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_AUDIOBOOK,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'course_id' => null,
            'bundle_id' => null,
            'unit_price' => 15.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 15.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.item_type',
                'audiobook'
            )
            ->assertJsonPath(
                'data.items.0.audiobook.id',
                $audiobook->id
            )
            ->assertJsonPath(
                'data.items.0.audiobook.uuid',
                $audiobook->uuid
            )
            ->assertJsonPath(
                'data.items.0.audiobook.book_id',
                $book->id
            )
            ->assertJsonPath(
                'data.items.0.audiobook.price',
                '15.00'
            )
            ->assertJsonPath(
                'data.items.0.audiobook.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.items.0.audiobook.duration_seconds',
                3600
            )
            ->assertJsonPath(
                'data.items.0.audiobook.duration_minutes',
                60
            )
            ->assertJsonPath(
                'data.items.0.book',
                null
            );
    }

    /**
     * Order details include a course item.
     *
     * This test intentionally establishes the API contract
     * for course order items.
     */
    public function test_order_details_include_course_item(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'title' => 'Kingdom Leadership',
            'slug' => 'kingdom-leadership',
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000202',
            'currency' => 'USD',
            'subtotal' => 30.00,
            'discount' => 0.00,
            'total' => 30.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_COURSE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => $course->id,
            'bundle_id' => null,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.item_type',
                'course'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                1
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '30.00'
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                '30.00'
            );
    }

    /**
     * Order details include a bundle item.
     *
     * This test establishes the customer-facing bundle
     * order-item contract.
     */
    public function test_order_details_include_bundle_item(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'name' => 'Kingdom Success Collection',
            'slug' => 'kingdom-success-collection',
            'description' => 'A collection of kingdom success resources.',
            'price' => 50.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000203',
            'currency' => 'USD',
            'subtotal' => 50.00,
            'discount' => 0.00,
            'total' => 50.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => $bundle->id,
            'unit_price' => 50.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 50.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath(
                'data.items.0.item_type',
                'bundle'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                1
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '50.00'
            )
            ->assertJsonPath(
                'data.items.0.subtotal',
                '50.00'
            );
    }

    /**
     * A mixed order can contain a book, audiobook,
     * course and bundle at the same time.
     *
     * This is the important integration contract for
     * the commercial catalogue.
     */
    public function test_order_details_include_mixed_product_types(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Foundations of Dominion',
        ]);

        $audiobookBook = Book::factory()->create();

        $audiobook = Audiobook::factory()->create([
            'book_id' => $audiobookBook->id,
            'price' => 15.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 1800,
            'published_at' => now(),
        ]);

        $course = Course::factory()->create([
            'title' => 'Kingdom Leadership',
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'published_at' => now(),
        ]);

        $bundle = Bundle::factory()->create([
            'name' => 'Kingdom Success Collection',
            'price' => 50.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $bundleBook = Book::factory()->create();

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $bundleBook->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'DP-000204',
            'currency' => 'USD',
            'subtotal' => 115.00,
            'discount' => 0.00,
            'total' => 115.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => null,
            'unit_price' => 20.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_AUDIOBOOK,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'course_id' => null,
            'bundle_id' => null,
            'unit_price' => 15.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 15.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_COURSE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => $course->id,
            'bundle_id' => null,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => $bundle->id,
            'unit_price' => 50.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 50.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data.items')
            ->assertJsonPath(
                'data.items.0.item_type',
                'book'
            )
            ->assertJsonPath(
                'data.items.1.item_type',
                'audiobook'
            )
            ->assertJsonPath(
                'data.items.2.item_type',
                'course'
            )
            ->assertJsonPath(
                'data.items.3.item_type',
                'bundle'
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

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => null,
            'unit_price' => $book->price,
            'currency' => $book->currency,
            'quantity' => 1,
            'subtotal' => $book->price,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders');

        $response->assertOk();

        $this->assertArrayNotHasKey(
            'items',
            $response->json('data.0')
        );
    }
}