<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\Book;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a basic paid book order.
     */
    private function createBookOrder(
        User $user,
        float $subtotal = 100.00
    ): Order {
        $book = Book::factory()->create([
            'price' => $subtotal,
            'is_published' => true,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-' . fake()->unique()->numerify('######'),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'audiobook_id' => null,
            'course_id' => null,
            'unit_price' => $subtotal,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => $subtotal,
        ]);

        return $order->fresh();
    }

    /**
     * Customer can validate a valid percentage coupon.
     */
    public function test_customer_can_validate_percentage_coupon(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'SAVE10',
            'name' => 'Ten Percent Off',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            'save10',
            $user,
            $order
        );

        $this->assertSame(
            'SAVE10',
            $result['coupon']->code
        );

        $this->assertSame(
            10.00,
            $result['discount']
        );

        $this->assertSame(
            90.00,
            $result['total']
        );
    }

    /**
     * Customer can validate a fixed coupon.
     */
    public function test_customer_can_validate_fixed_coupon(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'TAKE25',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 25,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            'TAKE25',
            $user,
            $order
        );

        $this->assertSame(
            25.00,
            $result['discount']
        );

        $this->assertSame(
            75.00,
            $result['total']
        );
    }

    /**
     * Coupon codes are case insensitive.
     */
    public function test_coupon_code_is_case_insensitive(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'WELCOME20',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            '  welcome20  ',
            $user,
            $order
        );

        $this->assertSame(
            20.00,
            $result['discount']
        );
    }

    /**
     * Invalid coupon codes are rejected.
     */
    public function test_invalid_coupon_code_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        app(CouponService::class)->validate(
            'DOESNOTEXIST',
            $user,
            $order
        );
    }

    /**
     * Inactive coupons are rejected.
     */
    public function test_inactive_coupon_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'INACTIVE',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => false,
        ]);

        app(CouponService::class)->validate(
            'INACTIVE',
            $user,
            $order
        );
    }

    /**
     * Future coupons are rejected.
     */
    public function test_future_coupon_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'FUTURE',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        app(CouponService::class)->validate(
            'FUTURE',
            $user,
            $order
        );
    }

    /**
     * Expired coupons are rejected.
     */
    public function test_expired_coupon_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'EXPIRED',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        app(CouponService::class)->validate(
            'EXPIRED',
            $user,
            $order
        );
    }

    /**
     * Global usage limits are enforced.
     */
    public function test_global_usage_limit_is_enforced(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'LIMITED',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
            'usage_limit' => 2,
            'usage_count' => 2,
        ]);

        app(CouponService::class)->validate(
            'LIMITED',
            $user,
            $order
        );
    }

    /**
     * Per-user usage limits are enforced.
     */
    public function test_per_user_usage_limit_is_enforced(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        $coupon = Coupon::create([
            'code' => 'ONCE',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
            'per_user_limit' => 1,
        ]);

        $previousOrder = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-' . fake()->unique()->numerify('######'),
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'USD',
            'subtotal' => 100,
            'discount' => 10,
            'total' => 90,
            'paid_at' => now(),
        ]);

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $previousOrder->id,
            'discount_amount' => 10,
            'coupon_code' => $coupon->code,
        ]);

        app(CouponService::class)->validate(
            'ONCE',
            $user,
            $order
        );
    }

    /**
     * Minimum order amounts are enforced.
     */
    public function test_minimum_order_amount_is_enforced(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            50
        );

        Coupon::create([
            'code' => 'MIN100',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'minimum_order_amount' => 100,
            'is_active' => true,
        ]);

        app(CouponService::class)->validate(
            'MIN100',
            $user,
            $order
        );
    }

    /**
     * Product restrictions are enforced.
     */
    public function test_product_type_restriction_is_enforced(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'AUDIO10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'product_type' => Coupon::PRODUCT_AUDIOBOOK,
            'is_active' => true,
        ]);

        app(CouponService::class)->validate(
            'AUDIO10',
            $user,
            $order
        );
    }

    /**
     * Matching product restrictions are accepted.
     */
    public function test_matching_product_type_restriction_is_accepted(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'price' => 100,
            'is_published' => true,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'DP-' . fake()->unique()->numerify('######'),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItem::TYPE_BOOK,
            'book_id' => $book->id,
            'unit_price' => 100,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 100,
        ]);

        Coupon::create([
            'code' => 'BOOK10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'product_type' => Coupon::PRODUCT_BOOK,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            'BOOK10',
            $user,
            $order
        );

        $this->assertSame(
            10.00,
            $result['discount']
        );
    }

    /**
     * Maximum discount is respected.
     */
    public function test_maximum_discount_is_respected(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            1000
        );

        Coupon::create([
            'code' => 'MAX50',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 50,
            'maximum_discount' => 50,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            'MAX50',
            $user,
            $order
        );

        $this->assertSame(
            50.00,
            $result['discount']
        );

        $this->assertSame(
            950.00,
            $result['total']
        );
    }

    /**
     * Discount cannot exceed the order subtotal.
     */
    public function test_discount_cannot_exceed_order_subtotal(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            20
        );

        Coupon::create([
            'code' => 'TAKE100',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 100,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->validate(
            'TAKE100',
            $user,
            $order
        );

        $this->assertSame(
            20.00,
            $result['discount']
        );

        $this->assertSame(
            0.00,
            $result['total']
        );
    }

    /**
     * Applying a coupon updates the order.
     */
    public function test_apply_updates_order_discount_and_total(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'SAVE20',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $result = app(CouponService::class)->apply(
            'SAVE20',
            $user,
            $order
        );

        $order->refresh();

        $this->assertSame(
            20.00,
            (float) $order->discount
        );

        $this->assertSame(
            80.00,
            (float) $order->total
        );

        $this->assertSame(
            20.00,
            $result['discount']
        );

        $this->assertSame(
            80.00,
            $result['total']
        );
    }

    /**
     * Applying a coupon records usage.
     */
    public function test_apply_records_coupon_usage(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        $coupon = Coupon::create([
            'code' => 'SAVE15',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'is_active' => true,
        ]);

        app(CouponService::class)->apply(
            'SAVE15',
            $user,
            $order
        );

        $this->assertDatabaseHas(
            'coupon_usages',
            [
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => 15.00,
                'coupon_code' => 'SAVE15',
            ]
        );

        $this->assertDatabaseHas(
            'coupons',
            [
                'id' => $coupon->id,
                'usage_count' => 1,
            ]
        );
    }

    /**
     * The same order cannot receive two coupons.
     */
    public function test_order_cannot_have_two_coupons(): void
    {
        $this->expectException(RuntimeException::class);

        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        Coupon::create([
            'code' => 'FIRST10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'SECOND20',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $service = app(CouponService::class);

        $service->apply(
            'FIRST10',
            $user,
            $order
        );

        $service->apply(
            'SECOND20',
            $user,
            $order->fresh()
        );
    }

    /**
     * Recording usage increments usage_count.
     */
    public function test_record_usage_increments_usage_count(): void
    {
        $user = User::factory()->create();

        $order = $this->createBookOrder(
            $user,
            100
        );

        $coupon = Coupon::create([
            'code' => 'REDEEM10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $usage = app(CouponService::class)->recordUsage(
            $coupon,
            $user,
            $order,
            10
        );

        $this->assertInstanceOf(
            CouponUsage::class,
            $usage
        );

        $this->assertSame(
            1,
            $coupon->fresh()->usage_count
        );
    }
}