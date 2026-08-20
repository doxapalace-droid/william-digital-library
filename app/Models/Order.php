<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Determine whether this order contains
     * at least one course item.
     */
    public function hasCourse(): bool
    {
        return $this->items()
            ->where('item_type', 'course')
            ->exists();
    }

    /**
     * Get course items contained in this order.
     */
    public function courseItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)
            ->where('item_type', 'course');
    }


    /**
 * Coupon redemption associated with this order.
 */
    public function couponUsage(): HasOne
    {
    return $this->hasOne(CouponUsage::class);
    
    }


    /**
    * Coupon redemptions associated with this order.
    */
    public function couponUsages(): HasMany
    {
    return $this->hasMany(CouponUsage::class);
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