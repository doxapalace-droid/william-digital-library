<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'currency',
        'subtotal',
        'discount',
        'total',
        'paid_at',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
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
     * Customer who placed the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Items contained in the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Payments associated with this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Coupon redemption associated with this order.
     *
     * An order can have at most one coupon redemption
     * because the checkout system allows one coupon
     * per order.
     */
    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    /**
     * Coupon redemptions associated with this order.
     *
     * Kept as a collection relationship for flexibility
     * and historical queries.
     */
    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Determine whether the order has been paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Determine whether the order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine whether the order contains
     * at least one course item.
     */
    public function hasCourse(): bool
    {
        return $this->items()
            ->where('item_type', OrderItem::TYPE_COURSE)
            ->exists();
    }

    /**
     * Get course items contained in this order.
     */
    public function courseItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->where('item_type', OrderItem::TYPE_COURSE);
    }

    /**
     * Determine whether the order contains
     * at least one bundle item.
     */
    public function hasBundle(): bool
    {
        return $this->items()
            ->where('item_type', OrderItem::TYPE_BUNDLE)
            ->exists();
    }

    /**
     * Get bundle items contained in this order.
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->where('item_type', OrderItem::TYPE_BUNDLE);
    }

    /**
     * Determine whether the order contains
     * at least one book item.
     */
    public function hasBook(): bool
    {
        return $this->items()
            ->where('item_type', OrderItem::TYPE_BOOK)
            ->exists();
    }

    /**
     * Get book items contained in this order.
     */
    public function bookItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->where('item_type', OrderItem::TYPE_BOOK);
    }

    /**
     * Determine whether the order contains
     * at least one audiobook item.
     */
    public function hasAudiobook(): bool
    {
        return $this->items()
            ->where('item_type', OrderItem::TYPE_AUDIOBOOK)
            ->exists();
    }

    /**
     * Get audiobook items contained in this order.
     */
    public function audiobookItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->where('item_type', OrderItem::TYPE_AUDIOBOOK);
    }

    /**
     * Determine whether the order contains
     * any digital product.
     */
    public function hasDigitalProducts(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Determine whether the order has a coupon discount.
     */
    public function hasDiscount(): bool
    {
        return (float) $this->discount > 0;
    }

    /**
     * Determine whether the order can still be paid.
     */
    public function canBePaid(): bool
    {
        return in_array(
            $this->payment_status,
            [
                'unpaid',
                'pending',
                'failed',
            ],
            true
        )
        && ! in_array(
            $this->status,
            [
                'cancelled',
                'completed',
            ],
            true
        );
    }
}