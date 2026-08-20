<?php

namespace Tests\Feature;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Coupon can be created.
     */
    public function test_coupon_can_be_created(): void
    {
        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'name' => 'Welcome Discount',
            'description' => '10 percent welcome discount.',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'maximum_discount' => null,
            'minimum_order_amount' => null,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'per_user_limit' => 1,
            'usage_count' => 0,
            'product_type' => null,
        ]);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'WELCOME10',
        ]);
    }

    /**
     * Percentage coupons are identified correctly.
     */
    public function test_percentage_coupon_is_identified_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->assertTrue($coupon->isPercentage());
        $this->assertFalse($coupon->isFixed());
    }

    /**
     * Fixed coupons are identified correctly.
     */
    public function test_fixed_coupon_is_identified_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE5',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 5,
        ]);

        $this->assertTrue($coupon->isFixed());
        $this->assertFalse($coupon->isPercentage());
    }

    /**
     * Active coupon is currently valid.
     */
    public function test_active_coupon_is_currently_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'ACTIVE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->isCurrentlyValid());
    }

    /**
     * Inactive coupon is not currently valid.
     */
    public function test_inactive_coupon_is_not_currently_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'INACTIVE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => false,
        ]);

        $this->assertFalse($coupon->isCurrentlyValid());
    }

    /**
     * Future coupon is not currently valid.
     */
    public function test_future_coupon_is_not_currently_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'FUTURE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at' => now()->addDay(),
        ]);

        $this->assertFalse($coupon->isCurrentlyValid());
    }

    /**
     * Expired coupon is not currently valid.
     */
    public function test_expired_coupon_is_not_currently_valid(): void
    {
        $coupon = Coupon::create([
            'code' => 'EXPIRED10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($coupon->isCurrentlyValid());
    }

    /**
     * Usage limit is detected correctly.
     */
    public function test_coupon_usage_limit_is_detected(): void
    {
        $coupon = Coupon::create([
            'code' => 'LIMITED10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'usage_limit' => 10,
            'usage_count' => 10,
        ]);

        $this->assertTrue($coupon->hasReachedUsageLimit());
    }

    /**
     * Coupon below its usage limit is still available.
     */
    public function test_coupon_below_usage_limit_is_available(): void
    {
        $coupon = Coupon::create([
            'code' => 'AVAILABLE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'usage_limit' => 10,
            'usage_count' => 9,
        ]);

        $this->assertFalse($coupon->hasReachedUsageLimit());
    }

    /**
     * Unlimited coupon never reaches a usage limit.
     */
    public function test_unlimited_coupon_has_no_usage_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'UNLIMITED10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'usage_limit' => null,
            'usage_count' => 999999,
        ]);

        $this->assertFalse($coupon->hasReachedUsageLimit());
    }

    /**
     * Minimum order amount is enforced.
     */
    public function test_coupon_checks_minimum_order_amount(): void
    {
        $coupon = Coupon::create([
            'code' => 'MIN50',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'minimum_order_amount' => 50,
        ]);

        $this->assertFalse(
            $coupon->meetsMinimumOrderAmount(49.99)
        );

        $this->assertTrue(
            $coupon->meetsMinimumOrderAmount(50.00)
        );
    }

    /**
     * Product restriction works correctly.
     */
    public function test_coupon_can_be_restricted_to_product_type(): void
    {
        $coupon = Coupon::create([
            'code' => 'BOOK10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'product_type' => Coupon::PRODUCT_BOOK,
        ]);

        $this->assertTrue(
            $coupon->appliesToProductType(
                Coupon::PRODUCT_BOOK
            )
        );

        $this->assertFalse(
            $coupon->appliesToProductType(
                Coupon::PRODUCT_COURSE
            )
        );
    }

    /**
     * Coupon without a product restriction applies
     * to all supported product types.
     */
    public function test_unrestricted_coupon_applies_to_all_products(): void
    {
        $coupon = Coupon::create([
            'code' => 'ALL10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'product_type' => null,
        ]);

        $this->assertTrue(
            $coupon->appliesToProductType(
                Coupon::PRODUCT_BOOK
            )
        );

        $this->assertTrue(
            $coupon->appliesToProductType(
                Coupon::PRODUCT_AUDIOBOOK
            )
        );

        $this->assertTrue(
            $coupon->appliesToProductType(
                Coupon::PRODUCT_COURSE
            )
        );
    }

    /**
     * Percentage discount is calculated correctly.
     */
    public function test_percentage_discount_is_calculated_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->assertSame(
            10.00,
            $coupon->calculateDiscount(100)
        );

        $this->assertSame(
            25.00,
            $coupon->calculateDiscount(250)
        );
    }

    /**
     * Fixed discount is calculated correctly.
     */
    public function test_fixed_discount_is_calculated_correctly(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE5',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 5,
        ]);

        $this->assertSame(
            5.00,
            $coupon->calculateDiscount(100)
        );
    }

    /**
     * Fixed discount cannot exceed the order amount.
     */
    public function test_discount_cannot_exceed_order_amount(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE100',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 100,
        ]);

        $this->assertSame(
            25.00,
            $coupon->calculateDiscount(25)
        );
    }

    /**
     * Percentage coupon respects maximum discount.
     */
    public function test_percentage_coupon_respects_maximum_discount(): void
    {
        $coupon = Coupon::create([
            'code' => 'MAX20',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 50,
            'maximum_discount' => 20,
        ]);

        $this->assertSame(
            20.00,
            $coupon->calculateDiscount(100)
        );
    }

    /**
     * Coupon code normalization works correctly.
     */
    public function test_coupon_code_is_normalized(): void
    {
        $this->assertSame(
            'WELCOME10',
            Coupon::normalizeCode('  welcome10  ')
        );
    }
}