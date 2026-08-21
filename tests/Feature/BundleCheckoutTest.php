<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A customer can add a bundle to the cart and
     * complete checkout successfully.
     */
    public function test_customer_can_checkout_bundle(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'name' => 'Kingdom Leadership Bundle',
            'price' => 49.99,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => $bundle->price,
            'currency' => $bundle->currency,
            'quantity' => 1,
            'subtotal' => $bundle->price,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/checkout');

        $response->assertSuccessful();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'subtotal' => '49.99',
            'total' => '49.99',
            'discount' => '0.00',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => '49.99',
            'quantity' => 1,
            'subtotal' => '49.99',
            'currency' => 'USD',
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    /**
     * Checkout must preserve the bundle price captured
     * in the customer's cart.
     *
     * Changing the bundle's current price after it was
     * added to the cart must not change the checkout price.
     */
    public function test_checkout_uses_captured_bundle_price(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'price' => 49.99,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => 49.99,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 49.99,
        ]);

        /*
         * Simulate the bundle price changing after
         * the customer added it to the cart.
         */
        $bundle->update([
            'price' => 79.99,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/checkout');

        $response->assertSuccessful();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $this->assertSame(
            '49.99',
            (string) $order->subtotal
        );

        $this->assertSame(
            '49.99',
            (string) $order->total
        );

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => '49.99',
            'subtotal' => '49.99',
        ]);
    }

    /**
     * The bundle relationship on the resulting order item
     * must point to the correct bundle.
     */
    public function test_order_item_belongs_to_bundle_after_checkout(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'price' => 35.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => 35.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 35.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/checkout');

        $response->assertSuccessful();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $orderItem = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_type', OrderItem::TYPE_BUNDLE)
            ->first();

        $this->assertNotNull($orderItem);

        $this->assertTrue(
            $orderItem->bundle->is($bundle)
        );

        $this->assertSame(
            $bundle->id,
            $orderItem->bundle_id
        );
    }

    /**
     * A bundle checkout creates exactly one bundle
     * order item.
     */
    public function test_bundle_checkout_creates_one_order_item(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'price' => 25.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => 25.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/checkout');

        $response->assertSuccessful();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $thisItems = $order->items()
            ->where('item_type', OrderItem::TYPE_BUNDLE)
            ->get();

        $this->assertCount(1, $bundleItems = $bundleItems ?? $thisItems);

        $this->assertSame(
            $bundle->id,
            $bundleItems->first()->bundle_id
        );
    }

    /**
     * A bundle checkout does not mark the order as paid.
     *
     * Payment is a separate process.
     */
    public function test_bundle_checkout_does_not_mark_order_as_paid(): void
    {
        $user = User::factory()->create();

        $bundle = Bundle::factory()->create([
            'price' => 60.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => 60.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 60.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/checkout');

        $response->assertSuccessful();

        $order = Order::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);

        $this->assertFalse($order->isPaid());
    }
}