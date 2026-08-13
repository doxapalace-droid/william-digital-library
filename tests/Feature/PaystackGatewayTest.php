<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaystackGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.secret_key' => 'sk_test_example_key',
            'services.paystack.callback_url' => 'https://example.com/payment/callback',
        ]);
    }

    /**
     * Paystack initialization sends the correct payment information
     * and returns the authorization details.
     */
    public function test_paystack_can_initialize_payment(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,

            // IMPORTANT:
            // The test expects this exact reference to be sent to Paystack.
            'transaction_reference' => 'DP-TEST-123456',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' =>
                Http::response([
                    'status' => true,
                    'message' => 'Authorization URL created',
                    'data' => [
                        'authorization_url' =>
                            'https://checkout.paystack.com/test123',

                        'access_code' =>
                            'test_access_code',

                        'reference' =>
                            'DP-TEST-123456',
                    ],
                ], 200),
        ]);

        $gateway = new PaystackGateway();

        $result = $gateway->initialize($payment);

        $this->assertTrue(
            str_contains(
                $result['authorization_url'],
                'https://checkout.paystack.com'
            )
        );

        $this->assertSame(
            'https://checkout.paystack.com/test123',
            $result['authorization_url']
        );

        $this->assertSame(
            'test_access_code',
            $result['access_code']
        );

        $this->assertSame(
            'DP-TEST-123456',
            $result['reference']
        );

        Http::assertSent(function ($request) {
            return $request->url() ===
                'https://api.paystack.co/transaction/initialize'
                && $request->method() === 'POST'
                && $request['email'] === 'customer@example.com'
                && $request['amount'] === 10000
                && $request['currency'] === 'GHS'
                && $request['reference'] === 'DP-TEST-123456';
        });
    }

    /**
     * Paystack initialization includes the callback URL.
     */
    public function test_paystack_initialization_includes_callback_url(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' =>
                Http::response([
                    'status' => true,
                    'data' => [
                        'authorization_url' =>
                            'https://checkout.paystack.com/test',

                        'access_code' => 'test',

                        'reference' =>
                            'DP-CALLBACK-123456',
                    ],
                ], 200),
        ]);

        $gateway = new PaystackGateway();

        $gateway->initialize($payment);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() ===
                    'https://api.paystack.co/transaction/initialize'
                && $request['callback_url'] ===
                    'https://example.com/payment/callback';
        });
    }

    /**
     * Paystack initialization handles gateway errors.
     */
    public function test_paystack_initialization_handles_gateway_error(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' =>
                Http::response([
                    'status' => false,
                    'message' => 'Invalid key',
                ], 401),
        ]);

        $gateway = new PaystackGateway();

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'Invalid key'
        );

        $gateway->initialize($payment);
    }

    /**
     * A customer without an email cannot initialize
     * a Paystack payment.
     */
    public function test_paystack_initialization_requires_customer_email(): void
    {
        /*
         * Create a valid database user first.
         *
         * We do NOT try to save NULL into the email column.
         */
        $user = User::factory()->create();

        /*
         * Simulate a missing email in memory.
         */
        $user->email = null;

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
            'currency' => 'GHS',
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        /*
         * Make sure PaystackGateway uses the modified
         * in-memory user rather than loading it again
         * from the database.
         */
        $payment->setRelation('user', $user);

        Http::fake();

        $gateway = new PaystackGateway();

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'A valid customer email is required for payment.'
        );

        $gateway->initialize($payment);

        Http::assertNothingSent();
    }

    /**
     * Paystack verification recognizes a successful transaction.
     */
    public function test_paystack_can_verify_successful_payment(): void
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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
            'transaction_reference' => 'DP-VERIFY-123456',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => 'DP-VERIFY-123456',
                        'amount' => 10000,
                        'currency' => 'GHS',
                    ],
                ], 200),
        ]);

        $gateway = new PaystackGateway();

        $result = $gateway->verify($payment);

        $this->assertTrue(
            $result['successful']
        );

        $this->assertSame(
            'DP-VERIFY-123456',
            $result['reference']
        );

        $this->assertEquals(
            100.00,
            $result['amount']
        );

        $this->assertSame(
            'GHS',
            $result['currency']
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() ===
                    'https://api.paystack.co/transaction/verify/DP-VERIFY-123456';
        });
    }

    /**
     * Paystack verification recognizes a failed transaction.
     */
    public function test_paystack_can_verify_failed_payment(): void
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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
            'transaction_reference' => 'DP-FAILED-123456',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'failed',
                        'reference' => 'DP-FAILED-123456',
                        'amount' => 10000,
                        'currency' => 'GHS',
                    ],
                ], 200),
        ]);

        $gateway = new PaystackGateway();

        $result = $gateway->verify($payment);

        $this->assertFalse(
            $result['successful']
        );

        $this->assertSame(
            'DP-FAILED-123456',
            $result['reference']
        );

        $this->assertEquals(
            100.00,
            $result['amount']
        );

        $this->assertSame(
            'GHS',
            $result['currency']
        );
    }

    /**
     * Paystack verification handles an API error.
     */
    public function test_paystack_verification_handles_gateway_error(): void
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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
            'transaction_reference' => 'DP-ERROR-123456',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => false,
                    'message' => 'Transaction not found',
                ], 404),
        ]);

        $gateway = new PaystackGateway();

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'Transaction not found'
        );

        $gateway->verify($payment);
    }

    /**
     * A payment without a transaction reference
     * cannot be verified.
     */
    public function test_payment_without_reference_cannot_be_verified(): void
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
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 100.00,
            'transaction_reference' => null,
        ]);

        Http::fake();

        $gateway = new PaystackGateway();

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'Payment does not have a transaction reference.'
        );

        $gateway->verify($payment);

        Http::assertNothingSent();
    }

    /**
     * Paystack initialization sends the amount in subunits.
     */
    public function test_paystack_converts_amount_to_subunits(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'GHS',
            'total' => 25.50,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'GHS',
            'amount' => 25.50,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' =>
                Http::response([
                    'status' => true,
                    'data' => [
                        'authorization_url' =>
                            'https://checkout.paystack.com/test',

                        'access_code' => 'test',

                        'reference' =>
                            'DP-AMOUNT-123456',
                    ],
                ]),
        ]);

        $gateway = new PaystackGateway();

        $gateway->initialize($payment);

        Http::assertSent(function ($request) {
            return $request['amount'] === 2550;
        });
    }

    /**
     * The gateway rejects unsupported currencies.
     */
    public function test_paystack_rejects_unsupported_currency(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'EUR',
            'total' => 100.00,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'EUR',
            'amount' => 100.00,
        ]);

        Http::fake();

        $gateway = new PaystackGateway();

        $this->expectException(\RuntimeException::class);

        $this->expectExceptionMessage(
            'Unsupported Paystack currency: EUR.'
        );

        $gateway->initialize($payment);

        Http::assertNothingSent();
    }
}