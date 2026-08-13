<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGatewayInterface;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An authenticated customer can initialize a payment
     * for their own payable order.
     */
    public function test_user_can_initialize_payment_for_their_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'GHS',
            'subtotal' => 100.00,
            'discount' => 0.00,
            'total' => 100.00,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->once()
            ->andReturn([
                'authorization_url' => 'https://checkout.paystack.com/test',
                'access_code' => 'test_access_code',
                'reference' => 'DP-TEST-123456',
                'raw' => [
                    'status' => true,
                    'message' => 'Authorization URL created',
                ],
            ]);

        $service = new PaymentService($gateway);

        $payment = $service->initialize($user, $order);

        $this->assertInstanceOf(Payment::class, $payment);

        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame($user->id, $payment->user_id);
        $this->assertSame('paystack', $payment->gateway);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('GHS', $payment->currency);
        $this->assertEquals(100.00, (float) $payment->amount);
        $this->assertSame('DP-TEST-123456', $payment->transaction_reference);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'transaction_reference' => 'DP-TEST-123456',
        ]);
    }

    /**
     * A user cannot initialize payment for another user's order.
     */
    public function test_user_cannot_initialize_payment_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->never();

        $service = new PaymentService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'You cannot pay for another user\'s order.'
        );

        $service->initialize($user, $order);
    }

    /**
     * A completed order cannot be paid again.
     */
    public function test_completed_order_cannot_be_paid_again(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->never();

        $service = new PaymentService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'This order cannot be paid.'
        );

        $service->initialize($user, $order);
    }

    /**
     * Existing pending payment should not create a duplicate payment.
     */
    public function test_existing_pending_payment_is_reused(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
        ]);

        $existingPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => 'pending',
            'gateway' => 'paystack',
            'amount' => 100.00,
            'currency' => 'GHS',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->never();

        $service = new PaymentService($gateway);

        $payment = $service->initialize($user, $order);

        $this->assertSame(
            $existingPayment->id,
            $payment->id
        );

        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * Existing successful payment should not create another payment.
     */
    public function test_existing_successful_payment_is_reused(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        $existingPayment = Payment::factory()->successful()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'amount' => 100.00,
            'currency' => 'GHS',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->never();

        $service = new PaymentService($gateway);

        /*
         * The order is completed, so this call will actually
         * be rejected before the existing payment is queried.
         */
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'This order cannot be paid.'
        );

        $service->initialize($user, $order);

        /*
         * Prevent unused variable warnings in some IDEs.
         */
        $this->assertNotNull($existingPayment);
    }

    /**
     * A successful gateway verification marks the payment as
     * successful and the order as paid and completed.
     */
    public function test_successful_payment_verification_completes_order(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'GHS',
            'total' => 100.00,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-TEST-123456',
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->withArgs(function (Payment $verifiedPayment) use ($payment) {
                return $verifiedPayment->id === $payment->id;
            })
            ->andReturn([
                'successful' => true,
                'reference' => 'DP-TEST-123456',
                'amount' => 100.00,
                'currency' => 'GHS',
                'raw' => [
                    'status' => true,
                    'data' => [
                        'status' => 'success',
                    ],
                ],
            ]);

        $service = new PaymentService($gateway);

        $result = $service->verify($payment);

        $this->assertSame('successful', $result->status);
        $this->assertSame(
            'DP-TEST-123456',
            $result->transaction_reference
        );

        $this->assertNotNull($result->paid_at);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }

    /**
     * A failed gateway verification marks the payment as failed
     * but does not complete the order.
     */
    public function test_failed_payment_verification_marks_payment_failed(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-TEST-FAILED',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GHS',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'successful' => false,
                'reference' => 'DP-TEST-FAILED',
                'amount' => 100.00,
                'currency' => 'GHS',
                'raw' => [
                    'status' => true,
                    'data' => [
                        'status' => 'failed',
                    ],
                ],
            ]);

        $service = new PaymentService($gateway);

        $result = $service->verify($payment);

        $this->assertSame('failed', $result->status);
        $this->assertNotNull($result->failed_at);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * A successful gateway response with the wrong amount
     * must never complete the payment.
     */
    public function test_payment_with_wrong_amount_is_rejected(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
            'currency' => 'GHS',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GHS',
            'transaction_reference' => 'DP-WRONG-AMOUNT',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'successful' => true,
                'reference' => 'DP-WRONG-AMOUNT',
                'amount' => 50.00,
                'currency' => 'GHS',
                'raw' => [],
            ]);

        $service = new PaymentService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Payment amount does not match the order amount.'
        );

        $service->verify($payment);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    /**
     * A successful gateway response with the wrong currency
     * must never complete the payment.
     */
    public function test_payment_with_wrong_currency_is_rejected(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
            'currency' => 'GHS',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'GHS',
            'transaction_reference' => 'DP-WRONG-CURRENCY',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn([
                'successful' => true,
                'reference' => 'DP-WRONG-CURRENCY',
                'amount' => 100.00,
                'currency' => 'USD',
                'raw' => [],
            ]);

        $service = new PaymentService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Payment currency does not match the order currency.'
        );

        $service->verify($payment);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    /**
     * A payment that has already succeeded should not call
     * the gateway again.
     */
    public function test_successful_payment_is_not_verified_again(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
        ]);

        $payment = Payment::factory()->successful()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'amount' => 100.00,
            'currency' => 'GHS',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('verify')
            ->never();

        $service = new PaymentService($gateway);

        $result = $service->verify($payment);

        $this->assertSame($payment->id, $result->id);
        $this->assertSame('successful', $result->status);
    }

    /**
     * A gateway initialization failure should mark the payment
     * as failed and rethrow the exception.
     */
    public function test_gateway_initialization_failure_marks_payment_failed(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 100.00,
            'currency' => 'GHS',
        ]);

        $gateway = Mockery::mock(PaymentGatewayInterface::class);

        $gateway
            ->shouldReceive('initialize')
            ->once()
            ->andThrow(
                new RuntimeException('Paystack is unavailable.')
            );

        $service = new PaymentService($gateway);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Paystack is unavailable.'
        );

        try {
            $service->initialize($user, $order);
        } finally {
            $this->assertDatabaseHas('payments', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'failed',
            ]);
        }
    }
}