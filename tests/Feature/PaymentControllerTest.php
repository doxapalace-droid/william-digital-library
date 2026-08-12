<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest cannot initiate payment.
     */
    public function test_guest_cannot_initiate_payment(): void
    {
        $response = $this->postJson('/api/payments', [
            'order_uuid' => fake()->uuid(),
            'gateway' => 'paystack',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * User can initiate payment for their unpaid order.
     */
    public function test_user_can_initiate_payment_for_unpaid_order(): void
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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.gateway', 'paystack')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.amount', '20.00');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => '20.00',
        ]);
    }

    /**
     * User cannot pay another user's order.
     */
    public function test_user_cannot_pay_another_users_order(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'DP-000002',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('payments', [
            'order_id' => $order->id,
            'user_id' => $attacker->id,
        ]);
    }

    /**
     * User cannot initiate payment for a completed order.
     */
    public function test_completed_order_cannot_be_paid(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000003',
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('payments', [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Cancelled order cannot be paid.
     */
    public function test_cancelled_order_cannot_be_paid(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000004',
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('payments', [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Payment amount is taken from the order total.
     */
    public function test_payment_uses_order_total(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000005',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 35.00,
            'discount' => 5.00,
            'total' => 30.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', '30.00');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '30.00',
        ]);
    }

    /**
     * Duplicate pending payment is not created.
     */
    public function test_duplicate_pending_payment_is_not_created(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000006',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'A pending payment already exists for this order.'
            );

        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * Payment can be viewed by its owner.
     */
    public function test_user_can_view_their_payment(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000007',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/payments/{$payment->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.uuid', $payment->uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '20.00');
    }

    /**
     * User cannot view another user's payment.
     */
    public function test_user_cannot_view_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::create([
            'user_id' => $owner->id,
            'order_number' => 'DP-000008',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/payments/{$payment->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Verification does not falsely mark a payment as successful.
     */
    public function test_unconfigured_payment_verification_does_not_mark_payment_successful(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000009',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response
            ->assertStatus(501)
            ->assertJsonPath(
                'message',
                'Payment gateway verification has not been configured yet.'
            );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }
}