<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Paystack responses for the entire test.
     *
     * This prevents PHPUnit from making real requests
     * to Paystack.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => 'sk_test_fake_key',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' =>
                Http::response([
                    'status' => true,
                    'message' => 'Authorization URL created',
                    'data' => [
                        'authorization_url' =>
                            'https://checkout.paystack.com/test',
                        'access_code' => 'test_access_code',
                        'reference' => 'DP-TEST-REFERENCE',
                    ],
                ], 200),

            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => 'DP-TEST-REFERENCE',
                        'amount' => 3000,
                        'currency' => 'USD',
                    ],
                ], 200),
        ]);
    }

    /**
     * Create a customer.
     */
    private function createUser(): User
    {
        return User::factory()->create([
            'email' => 'customer@example.com',
        ]);
    }

    /**
     * Create a published book.
     */
    private function createBook(
        float $price = 30.00,
        string $currency = 'USD'
    ): Book {
        return Book::factory()->create([
            'price' => $price,
            'currency' => $currency,
            'is_published' => true,
        ]);
    }

    /**
     * Create a published and active audiobook.
     *
     * We deliberately create the audiobook directly rather
     * than using Audiobook::factory(), so this test does not
     * depend on an AudiobookFactory existing.
     */
    private function createAudiobook(
        float $price = 30.00,
        string $currency = 'USD'
    ): Audiobook {
        $book = $this->createBook(
            30.00,
            $currency
        );

        return Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => $price,
            'currency' => $currency,
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Create an unpaid order.
     */
    private function createOrder(
        User $user,
        float $subtotal = 30.00,
        float $discount = 0.00,
        float $total = 30.00,
        string $currency = 'USD'
    ): Order {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-' . strtoupper(
                uniqid()
            ),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'paid_at' => null,
        ]);
    }

    /**
     * Add a book to an order.
     */
    private function addOrderItem(
        Order $order,
        Book $book,
        float $price = 30.00
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'book',
            'book_id' => $book->id,
            'audiobook_id' => null,
            'unit_price' => $price,
            'currency' => $order->currency,
            'quantity' => 1,
            'subtotal' => $price,
        ]);
    }

    /**
     * Add an audiobook to an order.
     */
    private function addAudiobookOrderItem(
        Order $order,
        Audiobook $audiobook,
        float $price = 30.00
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'audiobook',
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'unit_price' => $price,
            'currency' => $order->currency,
            'quantity' => 1,
            'subtotal' => $price,
        ]);
    }

    /**
     * Guest cannot initiate payment.
     */
    public function test_guest_cannot_initiate_payment(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Authenticated customer can initiate payment
     * for their own unpaid order.
     */
    public function test_user_can_initiate_payment_for_unpaid_order(): void
    {
        $user = $this->createUser();

        $book = $this->createBook();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $this->addOrderItem(
            $order,
            $book,
            30.00
        );

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.order_id',
                $order->id
            )
            ->assertJsonPath(
                'data.gateway',
                'paystack'
            )
            ->assertJsonPath(
                'data.status',
                'pending'
            )
            ->assertJsonPath(
                'data.currency',
                'USD'
            )
            ->assertJsonPath(
                'data.amount',
                '30.00'
            );

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => '30.00',
        ]);
    }

    /**
     * Customer cannot pay another user's order.
     */
    public function test_user_cannot_pay_another_users_order(): void
    {
        $owner = $this->createUser();

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $order = $this->createOrder(
            $owner,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_uuid',
            ]);
    }

    /**
     * A completed order cannot be paid again.
     */
    public function test_completed_order_cannot_be_paid(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $order->update([
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_uuid',
            ]);
    }

    /**
     * A cancelled order cannot be paid.
     */
    public function test_cancelled_order_cannot_be_paid(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $order->update([
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_uuid',
            ]);
    }

    /**
     * Payment amount must come from the order total.
     */
    public function test_payment_uses_order_total(): void
    {
        $user = $this->createUser();

        $book = $this->createBook(
            50.00,
            'USD'
        );

        $order = $this->createOrder(
            $user,
            50.00,
            20.00,
            30.00,
            'USD'
        );

        $this->addOrderItem(
            $order,
            $book,
            50.00
        );

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.amount',
                '30.00'
            );

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => '30.00',
        ]);
    }

    /**
     * Existing pending payment should be reused.
     */
    public function test_duplicate_pending_payment_is_not_created(): void
    {
        $user = $this->createUser();

        $book = $this->createBook();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $this->addOrderItem(
            $order,
            $book,
            30.00
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-EXISTING-PENDING',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
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
            )
            ->assertJsonPath(
                'data.id',
                $payment->id
            );

        $this->assertDatabaseCount(
            'payments',
            1
        );
    }

    /**
     * Customer cannot create another payment
     * after a successful payment already exists.
     */
    public function test_already_paid_order_cannot_create_another_payment(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-PAID-ORDER',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 30.00,

            /*
             * Payment model casts this field to array.
             */
            'gateway_response' => json_encode([
                'status' => true,
            ]),

            'paid_at' => now(),
            'failed_at' => null,
        ]);

        $order->update([
            'payment_status' => 'paid',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/payments', [
            'order_uuid' => $order->uuid,
            'gateway' => 'paystack',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_uuid',
            ]);
    }

    /**
     * Customer can view their own payment.
     */
    public function test_user_can_view_their_payment(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder($user);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-VIEW-TEST',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/payments/{$payment->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $payment->uuid
            )
            ->assertJsonPath(
                'data.order_id',
                $order->id
            )
            ->assertJsonPath(
                'data.gateway',
                'paystack'
            )
            ->assertJsonPath(
                'data.status',
                'pending'
            );
    }

    /**
     * Customer cannot view another customer's payment.
     */
    public function test_user_cannot_view_another_users_payment(): void
    {
        $owner = $this->createUser();

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $order = $this->createOrder($owner);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-PRIVATE-TEST',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/payments/{$payment->uuid}"
        );

        $response->assertNotFound();
    }

    /**
     * Customer can view payment history for their order.
     */
    public function test_user_can_view_payment_history_for_their_order(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder($user);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-HISTORY-1',
            'status' => 'failed',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-HISTORY-2',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}/payments"
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Customer cannot view payment history
     * belonging to another customer.
     */
    public function test_user_cannot_view_another_users_payment_history(): void
    {
        $owner = $this->createUser();

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $order = $this->createOrder($owner);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $owner->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-PRIVATE-HISTORY',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/orders/{$order->uuid}/payments"
        );

        $response->assertNotFound();
    }

    /**
     * A payment can be verified successfully
     * through the fake Paystack gateway.
     */
    public function test_successful_payment_verification(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',

            /*
             * This MUST match the fake Paystack response.
             */
            'transaction_reference' => 'DP-TEST-REFERENCE',

            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * A failed Paystack verification must not mark
     * the payment as successful.
     */
    public function test_failed_payment_verification_does_not_mark_payment_successful(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'failed',
                        'reference' => 'DP-FAILED-REFERENCE',
                        'amount' => 3000,
                        'currency' => 'USD',
                    ],
                ], 200),
        ]);

        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-FAILED-REFERENCE',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * Successful payment should create an entitlement
     * for the purchased book.
     */
    public function test_successful_payment_creates_book_entitlement(): void
    {
        $user = $this->createUser();

        $book = $this->createBook(
            30.00,
            'USD'
        );

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $this->addOrderItem(
            $order,
            $book,
            30.00
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',

            /*
             * MUST match the fake Paystack response.
             */
            'transaction_reference' => 'DP-TEST-REFERENCE',

            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        $this->assertDatabaseHas(
            'book_entitlements',
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
                'source' => 'purchase',
                'can_read' => true,
                'can_download' => true,
                'status' => 'active',
            ]
        );
    }

    /**
     * Successful payment should create an entitlement
     * for the purchased audiobook.
     *
     * IMPORTANT:
     * The payment reference MUST match the fake
     * Paystack verification response.
     */
    public function test_successful_payment_creates_audiobook_entitlement(): void
    {
        $user = $this->createUser();

        $audiobook = $this->createAudiobook(
            30.00,
            'USD'
        );

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $this->addAudiobookOrderItem(
            $order,
            $audiobook,
            30.00
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',

            /*
             * THIS IS THE IMPORTANT FIX.
             *
             * The fake Paystack verification response
             * returns DP-TEST-REFERENCE.
             */
            'transaction_reference' => 'DP-TEST-REFERENCE',

            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        /*
         * Confirm the audiobook entitlement was created.
         */
        $this->assertDatabaseHas(
            'audiobook_entitlements',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
                'source' => 'purchase',
                'can_stream' => true,
                'can_download' => true,
                'status' => 'active',
            ]
        );

        /*
         * Confirm the payment was marked successful.
         */
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
        ]);

        /*
         * Confirm the order was marked paid and completed.
         */
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);
    }

    /**
     * Payment verification must reject an amount
     * that does not match the order.
     */
    public function test_payment_verification_rejects_wrong_amount(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => 'DP-WRONG-AMOUNT',
                        'amount' => 5000,
                        'currency' => 'USD',
                    ],
                ], 200),
        ]);

        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-WRONG-AMOUNT',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * Payment verification must reject a currency
     * that does not match the order.
     */
    public function test_payment_verification_rejects_wrong_currency(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => 'DP-WRONG-CURRENCY',
                        'amount' => 3000,
                        'currency' => 'GHS',
                    ],
                ], 200),
        ]);

        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-WRONG-CURRENCY',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 30.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertUnprocessable();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'unpaid',
        ]);
    }

    /**
     * An already successful payment remains successful
     * and is not processed again.
     */
    public function test_already_successful_payment_is_handled_safely(): void
    {
        $user = $this->createUser();

        $order = $this->createOrder(
            $user,
            30.00,
            0.00,
            30.00,
            'USD'
        );

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-ALREADY-SUCCESSFUL',
            'status' => 'successful',
            'currency' => 'USD',
            'amount' => 30.00,

            /*
             * Store JSON because Payment casts this
             * field to array.
             */
            'gateway_response' => json_encode([
                'status' => true,
            ]),

            'paid_at' => now(),
            'failed_at' => null,
        ]);

        Sanctum::actingAs($user);

        /*
         * VERIFY IS A POST ENDPOINT.
         */
        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Payment has already been verified successfully.'
            );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
        ]);
    }
}