<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     */
    public const PRODUCT_BOOK = 'book';

    public const PRODUCT_AUDIOBOOK = 'audiobook';

    public const PRODUCT_COURSE = 'course';

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
     * Use UUID for route model binding.
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
    public function appliesToProductType(string $productType): bool
    {
        return $this->product_type === null
            || $this->product_type === $productType;
    }

    /**
     * Determine whether the coupon applies to
     * a particular order subtotal.
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
    public function calculateDiscount(float $amount): float
    {
        if ($amount <= 0) {
            return 0.00;
        }

        if ($this->isPercentage()) {
            $discount = $amount
                * ((float) $this->discount_value / 100);
        } elseif ($this->isFixed()) {
            $discount = (float) $this->discount_value;
        } else {
            return 0.00;
        }

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
        $discount = min($discount, $amount);

        return round($discount, 2);
    }

    /**
     * Normalize a coupon code.
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}