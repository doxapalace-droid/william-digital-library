<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin user.
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
     * Create a customer user.
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
     * Guest cannot view admin orders.
     */
    public function test_guest_cannot_view_admin_orders(): void
    {
        $response = $this->getJson('/api/admin/orders');

        $response->assertUnauthorized();
    }

    /**
     * Customer cannot view admin orders.
     */
    public function test_customer_cannot_view_admin_orders(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $response = $this->getJson('/api/admin/orders');

        $response->assertForbidden();
    }

    /**
     * Admin can view all orders.
     */
    public function test_admin_can_view_all_orders(): void
    {
        $admin = $this->createAdmin();

        $customerOne = User::factory()->create();
        $customerTwo = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customerOne->id,
            'order_number' => 'DP-000001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 30.00,
            'discount' => 0.00,
            'total' => 30.00,
        ]);

        Order::factory()->create([
            'user_id' => $customerTwo->id,
            'order_number' => 'DP-000002',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 40.00,
            'discount' => 0.00,
            'total' => 40.00,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/orders');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.total',
                2
            );
    }

    /**
     * Admin order list includes customer information.
     */
    public function test_admin_order_list_includes_customer_information(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create([
            'name' => 'John Customer',
            'email' => 'john@example.com',
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000010',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/orders');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.customer.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.0.customer.name',
                'John Customer'
            )
            ->assertJsonPath(
                'data.0.customer.email',
                'john@example.com'
            );
    }

    /**
     * Admin can search orders by order number.
     */
    public function test_admin_can_search_orders(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-SEARCH-001',
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-OTHER-002',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/orders?search=SEARCH-001'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.order_number',
                'DP-SEARCH-001'
            );
    }

    /**
     * Admin can filter orders by order status.
     */
    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-PENDING',
            'status' => 'pending',
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-COMPLETED',
            'status' => 'completed',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/orders?status=completed'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.order_number',
                'DP-COMPLETED'
            )
            ->assertJsonPath(
                'data.0.status',
                'completed'
            );
    }

    /**
     * Admin can filter orders by payment status.
     */
    public function test_admin_can_filter_orders_by_payment_status(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-PAID',
            'payment_status' => 'paid',
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-UNPAID',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/orders?payment_status=paid'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.order_number',
                'DP-PAID'
            )
            ->assertJsonPath(
                'data.0.payment_status',
                'paid'
            );
    }

    /**
     * Admin order list is paginated.
     */
    public function test_admin_orders_are_paginated(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        for ($i = 1; $i <= 25; $i++) {
            Order::factory()->create([
                'user_id' => $customer->id,
                'order_number' => sprintf(
                    'DP-%06d',
                    $i
                ),
            ]);
        }

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/orders?per_page=10'
        );

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
                25
            )
            ->assertJsonPath(
                'meta.last_page',
                3
            );
    }

    /**
     * Admin can view one order.
     */
    public function test_admin_can_view_an_order(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create([
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000100',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 50.00,
            'discount' => 5.00,
            'total' => 45.00,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            "/api/admin/orders/{$order->uuid}"
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
                'data.customer.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.customer.name',
                'Jane Customer'
            )
            ->assertJsonPath(
                'data.customer.email',
                'jane@example.com'
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
                '50.00'
            )
            ->assertJsonPath(
                'data.discount',
                '5.00'
            )
            ->assertJsonPath(
                'data.total',
                '45.00'
            );
    }

    /**
     * Admin order details include order items.
     */
    public function test_admin_order_details_include_items(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Kingdom Dominion',
            'slug' => 'kingdom-dominion',
            'subtitle' => 'Walking in Authority',
            'author' => 'William K. Danquah',
            'cover_image' => 'covers/kingdom-dominion.jpg',
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000200',
            'currency' => 'USD',
            'subtotal' => 25.00,
            'discount' => 0.00,
            'total' => 25.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            "/api/admin/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.items'
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
            );
    }

    /**
     * Admin order details include payments.
     */
    public function test_admin_order_details_include_payments(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000300',
            'currency' => 'USD',
            'total' => 30.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-TEST-001',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => json_encode([
                'status' => true,
            ]),
            'paid_at' => now(),
            'failed_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            "/api/admin/orders/{$order->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.payments'
            )
            ->assertJsonPath(
                'data.payments.0.gateway',
                'paystack'
            )
            ->assertJsonPath(
                'data.payments.0.transaction_reference',
                'DP-TEST-001'
            )
            ->assertJsonPath(
                'data.payments.0.status',
                'successful'
            )
            ->assertJsonPath(
                'data.payments.0.amount',
                '30.00'
            );
    }

    /**
     * Admin can update order status.
     */
    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000400',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'status' => 'processing',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Order updated successfully.'
            )
            ->assertJsonPath(
                'data.status',
                'processing'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);
    }

    /**
     * Admin can mark an order as paid.
     */
    public function test_admin_can_update_payment_status(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000500',
            'status' => 'processing',
            'payment_status' => 'pending',
            'paid_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'payment_status' => 'paid',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                'paid'
            );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);

        $this->assertNotNull(
            $order->fresh()->paid_at
        );
    }

    /**
     * Admin can update both order and payment status.
     */
    public function test_admin_can_update_order_and_payment_status(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000600',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'paid_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'status' => 'completed',
                'payment_status' => 'paid',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'completed'
            )
            ->assertJsonPath(
                'data.payment_status',
                'paid'
            );

        $freshOrder = $order->fresh();

        $this->assertSame(
            'completed',
            $freshOrder->status
        );

        $this->assertSame(
            'paid',
            $freshOrder->payment_status
        );

        $this->assertNotNull(
            $freshOrder->paid_at
        );
    }

    /**
     * Admin can mark a paid order as refunded.
     */
    public function test_admin_can_mark_payment_as_refunded(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000700',
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'payment_status' => 'refunded',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                'refunded'
            );

        $freshOrder = $order->fresh();

        $this->assertSame(
            'refunded',
            $freshOrder->payment_status
        );

        $this->assertNull(
            $freshOrder->paid_at
        );
    }

    /**
     * Customer cannot update an order.
     */
    public function test_customer_cannot_update_order(): void
    {
        $customer = $this->createCustomer();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'order_number' => 'DP-000800',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($customer);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'status' => 'completed',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Admin cannot view a nonexistent order.
     */
    public function test_admin_cannot_view_nonexistent_order(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/orders/00000000-0000-0000-0000-000000000000'
        );

        $response->assertNotFound();
    }

    /**
     * Invalid order status is rejected.
     */
    public function test_invalid_order_status_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'status' => 'invalid-status',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    /**
     * Invalid payment status is rejected.
     */
    public function test_invalid_payment_status_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'payment_status' => 'unpaid',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            "/api/admin/orders/{$order->uuid}",
            [
                'payment_status' => 'invalid-payment-status',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_status',
            ]);
    }
}