<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Supported discount types.
     */
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    /**
     * Supported product types.
     *
     * NULL means the coupon applies to all
     * supported product types.
     */
    public const PRODUCT_BOOK = 'book';

    public const PRODUCT_AUDIOBOOK = 'audiobook';

    public const PRODUCT_COURSE = 'course';

    public const PRODUCT_BUNDLE = 'bundle';

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'maximum_discount',
        'minimum_order_amount',
        'is_active',
        'starts_at',
        'expires_at',
        'usage_limit',
        'per_user_limit',
        'usage_count',
        'product_type',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',

            'is_active' => 'boolean',

            'starts_at' => 'datetime',
            'expires_at' => 'datetime',

            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Use UUID for public route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Coupon redemption records.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Determine whether this is a percentage coupon.
     */
    public function isPercentage(): bool
    {
        return $this->discount_type === self::TYPE_PERCENTAGE;
    }

    /**
     * Determine whether this is a fixed amount coupon.
     */
    public function isFixed(): bool
    {
        return $this->discount_type === self::TYPE_FIXED;
    }

    /**
     * Determine whether the discount type is supported.
     */
    public function hasSupportedDiscountType(): bool
    {
        return in_array(
            $this->discount_type,
            [
                self::TYPE_PERCENTAGE,
                self::TYPE_FIXED,
            ],
            true
        );
    }

    /**
     * Determine whether the coupon is currently
     * within its validity period.
     */
    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (
            $this->starts_at !== null
            && $this->starts_at->isFuture()
        ) {
            return false;
        }

        if (
            $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the coupon has reached
     * its global usage limit.
     */
    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null
            && $this->usage_count >= $this->usage_limit;
    }

    /**
     * Determine whether the coupon is restricted
     * to a particular product type.
     */
    public function appliesToProductType(
        string $productType
    ): bool {
        return $this->product_type === null
            || $this->product_type === $productType;
    }

    /**
     * Determine whether the coupon meets the
     * minimum order amount.
     */
    public function meetsMinimumOrderAmount(
        float $subtotal
    ): bool {
        return $this->minimum_order_amount === null
            || $subtotal >= (float) $this->minimum_order_amount;
    }

    /**
     * Calculate the discount for a given amount.
     */
    public function calculateDiscount(
        float $amount
    ): float {
        if ($amount <= 0) {
            return 0.00;
        }

        if (! $this->hasSupportedDiscountType()) {
            return 0.00;
        }

        /*
         * Percentage discount.
         */
        if ($this->isPercentage()) {
            $discount = $amount
                * ((float) $this->discount_value / 100);
        }

        /*
         * Fixed amount discount.
         */
        else {
            $discount = (float) $this->discount_value;
        }

        /*
         * Prevent negative discount values from
         * producing unexpected results.
         */
        $discount = max(
            $discount,
            0
        );

        /*
         * A percentage coupon may have a maximum
         * discount amount.
         */
        if (
            $this->maximum_discount !== null
            && $discount > (float) $this->maximum_discount
        ) {
            $discount = (float) $this->maximum_discount;
        }

        /*
         * A discount can never exceed the amount
         * being discounted.
         */
        $discount = min(
            $discount,
            $amount
        );

        return round(
            $discount,
            2
        );
    }

    /**
     * Normalize a coupon code.
     *
     * Coupon codes are case-insensitive and stored
     * consistently in uppercase.
     */
    public static function normalizeCode(
        string $code
    ): string {
        return strtoupper(
            trim($code)
        );
    }

    /**
     * Determine whether a product type is supported
     * by the coupon system.
     *
     * NULL means no product restriction.
     */
    public static function isSupportedProductType(
        ?string $productType
    ): bool {
        if ($productType === null) {
            return true;
        }

        return in_array(
            $productType,
            [
                self::PRODUCT_BOOK,
                self::PRODUCT_AUDIOBOOK,
                self::PRODUCT_COURSE,
                self::PRODUCT_BUNDLE,
            ],
            true
        );
    }

    /**
     * Determine whether this coupon has a
     * product restriction.
     */
    public function hasProductRestriction(): bool
    {
        return $this->product_type !== null;
    }

    /**
     * Determine whether this coupon applies
     * to all supported product types.
     */
    public function isUnrestricted(): bool
    {
        return $this->product_type === null;
    }

    /**
     * Determine whether this coupon applies
     * specifically to books.
     */
    public function appliesToBooks(): bool
    {
        return $this->product_type === self::PRODUCT_BOOK;
    }

    /**
     * Determine whether this coupon applies
     * specifically to audiobooks.
     */
    public function appliesToAudiobooks(): bool
    {
        return $this->product_type === self::PRODUCT_AUDIOBOOK;
    }

    /**
     * Determine whether this coupon applies
     * specifically to courses.
     */
    public function appliesToCourses(): bool
    {
        return $this->product_type === self::PRODUCT_COURSE;
    }

    /**
     * Determine whether this coupon applies
     * specifically to bundles.
     */
    public function appliesToBundles(): bool
    {
        return $this->product_type === self::PRODUCT_BUNDLE;
    }

    /**
     * Determine whether the coupon has a trial
     * or promotional restriction.
     *
     * Kept as a convenience method for future
     * coupon-management functionality.
     */
    public function isValidForAmount(
        float $subtotal
    ): bool {
        return $this->isCurrentlyValid()
            && $this->meetsMinimumOrderAmount(
                $subtotal
            )
            && ! $this->hasReachedUsageLimit();
    }
}