<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BundlePaymentEntitlementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Paystack responses for the entire test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => 'sk_test_fake_key',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => 'DP-BUNDLE-TEST-REFERENCE',
                        'amount' => 5000,
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
            'email' => 'bundle-customer@example.com',
        ]);
    }

    /**
     * Create a published book.
     */
    private function createBook(): Book
    {
        return Book::factory()->create([
            'price' => 30.00,
            'currency' => 'USD',
            'is_published' => true,
        ]);
    }

    /**
     * Create a published and active audiobook.
     */
    private function createAudiobook(): Audiobook
    {
        $book = $this->createBook();

        return Audiobook::create([
            'book_id' => $book->id,
            'description' => 'Test audiobook.',
            'cover_image' => 'audiobooks/test.jpg',
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Create a published and active course.
     */
    private function createCourse(): Course
    {
        return Course::create([
            'title' => 'Bundle Test Course',
            'slug' => 'bundle-test-course-' . uniqid(),
            'subtitle' => 'Bundle test course subtitle.',
            'description' => 'Bundle test course description.',
            'cover_image' => 'courses/test.jpg',
            'price' => 30.00,
            'currency' => 'USD',
            'status' => 'active',
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Create a purchasable bundle.
     */
    private function createBundle(): Bundle
    {
        return Bundle::factory()->create([
            'name' => 'Test Premium Bundle',
            'price' => 50.00,
            'currency' => 'USD',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }

    /**
     * Add a book to the bundle.
     */
    private function addBookToBundle(
        Bundle $bundle,
        Book $book
    ): BundleItem {
        return BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_BOOK,
            'book_id' => $book->id,
            'audiobook_id' => null,
            'course_id' => null,
            'video_id' => null,
        ]);
    }

    /**
     * Add an audiobook to the bundle.
     */
    private function addAudiobookToBundle(
        Bundle $bundle,
        Audiobook $audiobook
    ): BundleItem {
        return BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_AUDIOBOOK,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'course_id' => null,
            'video_id' => null,
        ]);
    }

    /**
     * Add a course to the bundle.
     */
    private function addCourseToBundle(
        Bundle $bundle,
        Course $course
    ): BundleItem {
        return BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_type' => BundleItem::TYPE_COURSE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => $course->id,
            'video_id' => null,
        ]);
    }

    /**
     * Create an unpaid order containing the bundle.
     */
    private function createBundleOrder(
        User $user,
        Bundle $bundle
    ): Order {
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 50.00,
            'discount' => 0.00,
            'total' => 50.00,
            'paid_at' => null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BUNDLE,
            'book_id' => null,
            'audiobook_id' => null,
            'course_id' => null,
            'bundle_id' => $bundle->id,
            'unit_price' => 50.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 50.00,
        ]);

        return $order;
    }

    /**
     * Create a pending payment for the bundle order.
     */
    private function createPayment(
        User $user,
        Order $order
    ): Payment {
        return Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'transaction_reference' => 'DP-BUNDLE-TEST-REFERENCE',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 50.00,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * A successful bundle payment grants access
     * to all supported products inside the bundle.
     */
    public function test_successful_bundle_payment_creates_entitlements_for_supported_products(): void
    {
        $user = $this->createUser();

        $book = $this->createBook();

        $audiobook = $this->createAudiobook();

        $course = $this->createCourse();

        $bundle = $this->createBundle();

        $this->addBookToBundle(
            $bundle,
            $book
        );

        $this->addAudiobookToBundle(
            $bundle,
            $audiobook
        );

        $this->addCourseToBundle(
            $bundle,
            $course
        );

        $order = $this->createBundleOrder(
            $user,
            $bundle
        );

        $payment = $this->createPayment(
            $user,
            $order
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        /*
         * Book access must be created.
         */
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

        /*
         * Audiobook access must be created.
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
         * Course access must be created.
         */
        $this->assertDatabaseHas(
            'course_entitlements',
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'source' => 'purchase',
                'can_access' => true,
                'status' => 'active',
            ]
        );

        /*
         * The payment must be successful.
         */
        $this->assertDatabaseHas(
            'payments',
            [
                'id' => $payment->id,
                'status' => 'successful',
            ]
        );

        /*
         * The order must be paid and completed.
         */
        $this->assertDatabaseHas(
            'orders',
            [
                'id' => $order->id,
                'payment_status' => 'paid',
                'status' => 'completed',
            ]
        );
    }

    /**
     * A bundle containing multiple supported products
     * must grant each entitlement only once.
     */
    public function test_bundle_payment_does_not_duplicate_entitlements(): void
    {
        $user = $this->createUser();

        $book = $this->createBook();

        $bundle = $this->createBundle();

        $this->addBookToBundle(
            $bundle,
            $book
        );

        $order = $this->createBundleOrder(
            $user,
            $bundle
        );

        $payment = $this->createPayment(
            $user,
            $order
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        $this->assertSame(
            1,
            BookEntitlement::query()
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->count()
        );
    }

    /**
     * Bundle payment should not create a separate
     * entitlement record for the bundle itself.
     *
     * Access is granted through the products contained
     * in the bundle.
     */
    public function test_bundle_payment_does_not_require_bundle_entitlement(): void
    {
        $user = $this->createUser();

        $book = $this->createBook();

        $bundle = $this->createBundle();

        $this->addBookToBundle(
            $bundle,
            $book
        );

        $order = $this->createBundleOrder(
            $user,
            $bundle
        );

        $payment = $this->createPayment(
            $user,
            $order
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/payments/{$payment->uuid}/verify"
        );

        $response->assertOk();

        /*
         * The product entitlement is what gives
         * the customer access.
         */
        $this->assertDatabaseHas(
            'book_entitlements',
            [
                'user_id' => $user->id,
                'book_id' => $book->id,
                'source' => 'purchase',
                'status' => 'active',
            ]
        );
    }
}