<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixedOrderPaymentEntitlementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Successful payment for a mixed order creates
     * entitlements for all purchased products.
     */
    public function test_successful_mixed_order_payment_creates_all_entitlements(): void
    {
        $user = User::factory()->create();

        /*
         * Direct book purchase.
         *
         * Books use is_published rather than status.
         */
        $book = Book::factory()->create([
            'price' => 20,
            'is_published' => true,
        ]);

        /*
         * Direct audiobook purchase.
         */
        $audiobook = Audiobook::factory()->create([
            'price' => 15,
            'status' => 'active',
        ]);

        /*
         * Direct course purchase.
         */
        $course = Course::factory()->create([
            'price' => 30,
            'status' => 'active',
        ]);

        /*
         * Bundle purchase.
         */
        $bundle = Bundle::factory()->create([
            'price' => 50,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        /*
         * Products contained inside the bundle.
         */
        $bundleBook = Book::factory()->create([
            'price' => 10,
            'is_published' => true,
        ]);

        $bundleAudiobook = Audiobook::factory()->create([
            'price' => 10,
            'status' => 'active',
        ]);

        $bundleCourse = Course::factory()->create([
            'price' => 20,
            'status' => 'active',
        ]);

        /*
         * Add products to the bundle.
         */
        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $bundleBook->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_AUDIOBOOK,
            'audiobook_id' => $bundleAudiobook->id,
        ]);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_COURSE,
            'course_id' => $bundleCourse->id,
        ]);

        /*
         * Create a mixed order containing:
         *
         * 1. Book
         * 2. Audiobook
         * 3. Course
         * 4. Bundle
         */
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 115,
            'discount' => 0,
            'total' => 115,
        ]);

        /*
         * Direct book order item.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'unit_price' => 20,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 20,
        ]);

        /*
         * Direct audiobook order item.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_AUDIOBOOK,
            'audiobook_id' => $audiobook->id,
            'unit_price' => 15,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 15,
        ]);

        /*
         * Direct course order item.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_COURSE,
            'course_id' => $course->id,
            'unit_price' => 30,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30,
        ]);

        /*
         * Bundle order item.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'bundle_id' => $bundle->id,
            'unit_price' => 50,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 50,
        ]);

        /*
         * Create the pending payment.
         */
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'MIXED-TEST-REFERENCE',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 115,
        ]);

        /*
         * Mock the payment gateway verification.
         */
        $gateway = $this->mock(
            PaymentGatewayInterface::class
        );

        $gateway->shouldReceive('verify')
            ->once()
            ->withArgs(function (
                Payment $verifiedPayment
            ) use ($payment) {
                return $verifiedPayment->id === $payment->id;
            })
            ->andReturn([
                'successful' => true,
                'reference' => 'MIXED-TEST-REFERENCE',
                'amount' => 115,
                'currency' => 'USD',
                'raw' => [
                    'status' => true,
                    'message' => 'Verification successful',
                ],
            ]);

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway
        );

        /*
         * Verify the payment.
         */
        $response = $this->actingAs($user)
            ->postJson(
                "/api/payments/{$payment->uuid}/verify"
            );

        $response->assertSuccessful();

        /*
         * Direct book entitlement.
         */
        $this->assertDatabaseHas(
            'book_entitlements',
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ]
        );

        /*
         * Direct audiobook entitlement.
         */
        $this->assertDatabaseHas(
            'audiobook_entitlements',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ]
        );

        /*
         * Direct course entitlement.
         */
        $this->assertDatabaseHas(
            'course_entitlements',
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]
        );

        /*
         * Book entitlement from the bundle.
         */
        $this->assertDatabaseHas(
            'book_entitlements',
            [
                'user_id' => $user->id,
                'book_id' => $bundleBook->id,
            ]
        );

        /*
         * Audiobook entitlement from the bundle.
         */
        $this->assertDatabaseHas(
            'audiobook_entitlements',
            [
                'user_id' => $user->id,
                'audiobook_id' => $bundleAudiobook->id,
            ]
        );

        /*
         * Course entitlement from the bundle.
         */
        $this->assertDatabaseHas(
            'course_entitlements',
            [
                'user_id' => $user->id,
                'course_id' => $bundleCourse->id,
            ]
        );

        /*
         * No bundle entitlement is checked because
         * the application does not have a bundle_entitlements
         * table. Bundle ownership is represented through
         * the entitlements created for its contained products.
         */
    }
}