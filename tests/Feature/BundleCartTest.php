<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleCartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an authenticated customer.
     */
    protected function authenticatedUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Create a published and purchasable bundle.
     */
    protected function purchasableBundle(
        array $attributes = []
    ): Bundle {
        return Bundle::factory()->create(array_merge([
            'price' => 49.99,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    /**
     * A customer can add a purchasable bundle
     * to their cart.
     */
    public function test_customer_can_add_bundle_to_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => '49.99',
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => '49.99',
        ]);
    }

    /**
     * A bundle cart item stores the correct
     * bundle relationship.
     */
    public function test_bundle_cart_item_belongs_to_bundle(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'price' => 75.00,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ])
            ->assertSuccessful();

        $cartItem = CartItem::query()
            ->where('user_id', $user->id)
            ->where('bundle_id', $bundle->id)
            ->firstOrFail();

        $this->assertTrue(
            $cartItem->isBundle()
        );

        $this->assertTrue(
            $cartItem->bundle->is($bundle)
        );
    }

    /**
     * The bundle price is captured when the bundle
     * is added to the cart.
     */
    public function test_bundle_price_is_captured_in_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'price' => 120.50,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ])
            ->assertSuccessful();

        /*
         * Change the catalogue price after the item
         * has been added.
         */
        $bundle->update([
            'price' => 200.00,
        ]);

        $cartItem = CartItem::query()
            ->where('user_id', $user->id)
            ->where('bundle_id', $bundle->id)
            ->firstOrFail();

        $this->assertSame(
            '120.50',
            (string) $cartItem->unit_price
        );

        $this->assertSame(
            '120.50',
            (string) $cartItem->subtotal
        );
    }

    /**
     * A customer cannot add the same bundle twice.
     */
    public function test_customer_cannot_add_same_bundle_twice(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle();

        $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ])
            ->assertSuccessful();

        $secondResponse = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ]);

        $secondResponse->assertStatus(422);

        $this->assertSame(
            1,
            CartItem::query()
                ->where('user_id', $user->id)
                ->where('bundle_id', $bundle->id)
                ->count()
        );
    }

    /**
     * An inactive bundle cannot be added to the cart.
     */
    public function test_inactive_bundle_cannot_be_added_to_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
        ]);
    }

    /**
     * An unpublished bundle cannot be added to the cart.
     */
    public function test_unpublished_bundle_cannot_be_added_to_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'is_published' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
        ]);
    }

    /**
     * A bundle scheduled for future publication cannot
     * be added to the cart.
     */
    public function test_future_bundle_cannot_be_added_to_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'published_at' => now()->addDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
        ]);
    }

    /**
     * A bundle appears in the customer's cart.
     */
    public function test_bundle_appears_in_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle([
            'name' => 'Kingdom Leadership Bundle',
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ])
            ->assertSuccessful();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/cart');

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'item_type' => CartItem::TYPE_BUNDLE,
        ]);

        $response->assertJsonFragment([
            'bundle_id' => $bundle->id,
        ]);
    }

    /**
     * A bundle can be removed from the cart.
     */
    public function test_bundle_can_be_removed_from_cart(): void
    {
        $user = $this->authenticatedUser();

        $bundle = $this->purchasableBundle();

        $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' => $bundle->uuid,
            ])
            ->assertSuccessful();

        $cartItem = CartItem::query()
            ->where('user_id', $user->id)
            ->where('bundle_id', $bundle->id)
            ->firstOrFail();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/cart/{$cartItem->uuid}"
            );

        $response->assertSuccessful();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    /**
     * A customer must be authenticated to add
     * a bundle to the cart.
     */
    public function test_guest_cannot_add_bundle_to_cart(): void
    {
        $bundle = $this->purchasableBundle();

        $response = $this->postJson('/api/cart', [
            'bundle_uuid' => $bundle->uuid,
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Bundle UUID is required when adding a bundle.
     */
    public function test_bundle_uuid_is_required(): void
    {
        $user = $this->authenticatedUser();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'bundle_uuid',
        ]);
    }

    /**
     * A nonexistent bundle cannot be added to the cart.
     */
    public function test_nonexistent_bundle_cannot_be_added_to_cart(): void
    {
        $user = $this->authenticatedUser();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/cart', [
                'bundle_uuid' =>
                    '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422);
    }
}