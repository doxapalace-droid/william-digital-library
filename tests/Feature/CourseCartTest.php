<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A guest cannot view the cart.
     */
    public function test_guest_cannot_view_cart(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertUnauthorized();
    }

    /**
     * A guest cannot add a course to the cart.
     */
    public function test_guest_cannot_add_course_to_cart(): void
    {
        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        $response = $this->postJson('/api/cart', [
            'item_type' => 'course',
            'course_id' => $course->uuid,
        ]);

        $response->assertUnauthorized();
    }

    /**
     * An authenticated customer can add an active course
     * to the cart.
     */
    public function test_user_can_add_course_to_cart(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'item_type' => CartItem::TYPE_COURSE,
            'quantity' => 1,
        ]);
    }

    /**
     * The captured course price is stored in the cart.
     */
    public function test_course_cart_item_keeps_captured_price(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 125.50,
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'unit_price' => '125.50',
            'subtotal' => '125.50',
            'currency' => $course->currency,
        ]);
    }

    /**
     * The same course cannot be added twice.
     */
    public function test_user_cannot_add_same_course_twice(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ])
            ->assertSuccessful();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('cart_items', 1);
    }

    /**
     * A draft course cannot be added.
     */
    public function test_draft_course_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'draft',
            'price' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * An inactive course cannot be added.
     */
    public function test_inactive_course_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'inactive',
            'price' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * A future course cannot be added.
     */
    public function test_future_course_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
            'published_at' => now()->addDay(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * A customer who already owns a course cannot
     * add it to the cart again.
     */
    public function test_customer_cannot_add_course_they_already_own(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'purchase',
            'can_access' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * An expired course entitlement does not prevent
     * the customer from purchasing the course again.
     */
    public function test_expired_entitlement_does_not_block_course_cart(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'purchase',
            'can_access' => true,
            'status' => 'active',
            'granted_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * A revoked entitlement does not prevent
     * the customer from purchasing the course again.
     */
    public function test_revoked_entitlement_does_not_block_course_cart(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        CourseEntitlement::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'purchase',
            'can_access' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => now(),
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/cart', [
                'item_type' => 'course',
                'course_id' => $course->uuid,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * A customer can view their course in the cart.
     */
    public function test_user_can_view_course_in_cart(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_COURSE,
            'course_id' => $course->id,
            'unit_price' => 100,
            'currency' => $course->currency,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertSuccessful();

        $response->assertJsonFragment([
            'item_type' => 'course',
        ]);
    }

    /**
     * A customer can remove a course from the cart.
     */
    public function test_user_can_remove_course_from_cart(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'status' => 'active',
            'price' => 100,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'item_type' => CartItem::TYPE_COURSE,
            'course_id' => $course->id,
            'unit_price' => 100,
            'currency' => $course->currency,
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/cart/{$cartItem->uuid}"
            );

        $response->assertSuccessful();

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }
}