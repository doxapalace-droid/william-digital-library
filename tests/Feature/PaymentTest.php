<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Payment belongs to an order.
     */
    public function test_payment_belongs_to_order(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000001',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000001',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        $this->assertTrue($payment->order->is($order));
    }

    /**
     * Payment belongs to a user.
     */
    public function test_payment_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000002',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000002',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        $this->assertTrue($payment->user->is($user));
    }

    /**
     * User can have multiple payments.
     */
    public function test_user_has_many_payments(): void
    {
        $user = User::factory()->create();

        $orderOne = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000003',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $orderTwo = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000004',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 30.00,
            'discount' => 0.00,
            'total' => 30.00,
        ]);

        Payment::create([
            'order_id' => $orderOne->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000003',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Payment::create([
            'order_id' => $orderTwo->id,
            'user_id' => $user->id,
            'gateway' => 'flutterwave',
            'transaction_reference' => 'TXN-000004',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 30.00,
        ]);

        $this->assertCount(2, $user->payments);
    }

    /**
     * Order can have multiple payments.
     */
    public function test_order_has_many_payments(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000005',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000005',
            'status' => 'failed',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000006',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        $this->assertCount(2, $order->payments);
    }

    /**
     * Payment amount is cast to decimal with two places.
     */
    public function test_payment_amount_is_cast_correctly(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000007',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 25.50,
            'discount' => 0.00,
            'total' => 25.50,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000007',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 25.50,
        ]);

        $this->assertSame('25.50', $payment->amount);
    }

    /**
     * Payment timestamps are cast correctly.
     */
    public function test_payment_timestamps_are_cast_correctly(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000008',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000008',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 20.00,
            'paid_at' => now(),
        ]);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $payment->paid_at
        );
    }

    /**
     * Successful payment is detected correctly.
     */
    public function test_successful_payment_is_detected(): void
    {
        $payment = new Payment([
            'status' => 'successful',
        ]);

        $this->assertTrue($payment->isSuccessful());
        $this->assertFalse($payment->isPending());
        $this->assertFalse($payment->isFailed());
    }

    /**
     * Pending payment is detected correctly.
     */
    public function test_pending_payment_is_detected(): void
    {
        $payment = new Payment([
            'status' => 'pending',
        ]);

        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isSuccessful());
        $this->assertFalse($payment->isFailed());
    }

    /**
     * Failed payment is detected correctly.
     */
    public function test_failed_payment_is_detected(): void
    {
        $payment = new Payment([
            'status' => 'failed',
        ]);

        $this->assertTrue($payment->isFailed());
        $this->assertFalse($payment->isSuccessful());
        $this->assertFalse($payment->isPending());
    }

    /**
     * Payment stores gateway information correctly.
     */
    public function test_payment_stores_gateway_information(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000009',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 50.00,
            'discount' => 0.00,
            'total' => 50.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'flutterwave',
            'transaction_reference' => 'FLW-000001',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 50.00,
        ]);

        $this->assertSame('flutterwave', $payment->gateway);
        $this->assertSame('FLW-000001', $payment->transaction_reference);
    }

    /**
     * Payment stores gateway response correctly.
     */
    public function test_payment_stores_gateway_response(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000010',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $gatewayResponse = json_encode([
            'status' => true,
            'message' => 'Payment successful',
            'reference' => 'TXN-000010',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000010',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 20.00,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now(),
        ]);

        $this->assertSame($gatewayResponse, $payment->gateway_response);
    }

    /**
     * Payment receives a UUID.
     */
    public function test_payment_has_uuid(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-000011',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 20.00,
            'discount' => 0.00,
            'total' => 20.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'TXN-000011',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 20.00,
        ]);

        $this->assertNotNull($payment->uuid);
        $this->assertNotEmpty($payment->uuid);
    }
}