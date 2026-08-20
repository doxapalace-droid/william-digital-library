<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CouponService
{
    /**
     * Validate a coupon against an order for a customer.
     *
     * This method does not record usage.
     * It only determines whether the coupon can be applied
     * and calculates the discount.
     *
     * @return array{
     *     coupon: Coupon,
     *     discount: float,
     *     subtotal: float,
     *     total: float
     * }
     */
    public function validate(
        string $code,
        User $user,
        Order $order
    ): array {
        $coupon = Coupon::query()
            ->where('code', Coupon::normalizeCode($code))
            ->first();

        if (! $coupon) {
            throw new RuntimeException(
                'Invalid coupon code.'
            );
        }

        $this->validateCoupon(
            $coupon,
            $user,
            $order
        );

        $subtotal = (float) $order->subtotal;

        $discount = $coupon->calculateDiscount(
            $subtotal
        );

        $total = max(
            $subtotal - $discount,
            0
        );

        return [
            'coupon' => $coupon,
            'discount' => round($discount, 2),
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Validate a coupon against an order.
     *
     * This method contains all coupon eligibility rules.
     */
    private function validateCoupon(
        Coupon $coupon,
        User $user,
        Order $order
    ): void {
        if (! $coupon->isCurrentlyValid()) {
            throw new RuntimeException(
                'This coupon is not currently valid.'
            );
        }

        if ($coupon->hasReachedUsageLimit()) {
            throw new RuntimeException(
                'This coupon has reached its usage limit.'
            );
        }

        if ($this->hasUserReachedLimit($coupon, $user)) {
            throw new RuntimeException(
                'You have already used this coupon the maximum number of times.'
            );
        }

        $subtotal = (float) $order->subtotal;

        if (! $coupon->meetsMinimumOrderAmount($subtotal)) {
            throw new RuntimeException(
                'The order does not meet the minimum amount required for this coupon.'
            );
        }

        $this->validateProductRestriction(
            $coupon,
            $order
        );
    }

    /**
     * Determine whether the customer has reached
     * the coupon's per-user usage limit.
     */
    private function hasUserReachedLimit(
        Coupon $coupon,
        User $user
    ): bool {
        if ($coupon->per_user_limit === null) {
            return false;
        }

        $usageCount = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->count();

        return $usageCount >= $coupon->per_user_limit;
    }

    /**
     * Validate product-type restrictions.
     *
     * If the coupon has no product restriction,
     * it applies to the entire order.
     *
     * If the coupon is restricted to one product type,
     * the order must contain at least one item of that type.
     */
    private function validateProductRestriction(
        Coupon $coupon,
        Order $order
    ): void {
        if ($coupon->product_type === null) {
            return;
        }

        $supportedTypes = [
            Coupon::PRODUCT_BOOK,
            Coupon::PRODUCT_AUDIOBOOK,
            Coupon::PRODUCT_COURSE,
        ];

        if (
            ! in_array(
                $coupon->product_type,
                $supportedTypes,
                true
            )
        ) {
            throw new RuntimeException(
                'This coupon has an invalid product restriction.'
            );
        }

        $hasMatchingProduct = $order->items()
            ->where(
                'item_type',
                $coupon->product_type
            )
            ->exists();

        if (! $hasMatchingProduct) {
            throw new RuntimeException(
                'This coupon does not apply to the products in your order.'
            );
        }
    }

    /**
     * Record successful coupon usage against an order.
     *
     * This should only be called after the coupon has
     * actually been applied to the order.
     */
    public function recordUsage(
        Coupon $coupon,
        User $user,
        Order $order,
        float $discount
    ): CouponUsage {
        return DB::transaction(function () use (
            $coupon,
            $user,
            $order,
            $discount
        ) {
            /*
             * Prevent the same coupon from being recorded
             * twice against the same order.
             */
            $existingUsage = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('order_id', $order->id)
                ->first();

            if ($existingUsage) {
                return $existingUsage;
            }

            /*
             * Re-check the global usage limit inside the
             * transaction before recording the redemption.
             */
            $lockedCoupon = Coupon::query()
                ->whereKey($coupon->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCoupon->hasReachedUsageLimit()) {
                throw new RuntimeException(
                    'This coupon has reached its usage limit.'
                );
            }

            /*
             * Re-check the per-user limit while holding
             * the coupon lock.
             */
            if (
                $lockedCoupon->per_user_limit !== null
                && CouponUsage::query()
                    ->where(
                        'coupon_id',
                        $lockedCoupon->id
                    )
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->count()
                    >= $lockedCoupon->per_user_limit
            ) {
                throw new RuntimeException(
                    'You have already used this coupon the maximum number of times.'
                );
            }

            $usage = CouponUsage::create([
                'coupon_id' => $lockedCoupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => round(
                    $discount,
                    2
                ),
                'coupon_code' => $lockedCoupon->code,
            ]);

            $lockedCoupon->increment(
                'usage_count'
            );

            return $usage->fresh();
        });
    }

    /**
     * Apply a coupon to an order and record its usage.
     *
     * This method validates the coupon, updates the order's
     * discount and total, and records the coupon usage in
     * one database transaction.
     *
     * This method should normally be used only when the
     * application has decided that the coupon is actually
     * being applied to the order.
     *
     * @return array{
     *     coupon: Coupon,
     *     usage: CouponUsage,
     *     discount: float,
     *     subtotal: float,
     *     total: float
     * }
     */
    public function apply(
        string $code,
        User $user,
        Order $order
    ): array {
        return DB::transaction(function () use (
            $code,
            $user,
            $order
        ) {
            $order->loadMissing('items');

            $coupon = Coupon::query()
                ->where(
                    'code',
                    Coupon::normalizeCode($code)
                )
                ->lockForUpdate()
                ->first();

            if (! $coupon) {
                throw new RuntimeException(
                    'Invalid coupon code.'
                );
            }

            $this->validateCoupon(
                $coupon,
                $user,
                $order
            );

            /*
             * Do not allow a second coupon to overwrite
             * an existing discount silently.
             */
            $existingUsage = CouponUsage::query()
                ->where('order_id', $order->id)
                ->first();

            if ($existingUsage) {
                throw new RuntimeException(
                    'A coupon has already been applied to this order.'
                );
            }

            $subtotal = (float) $order->subtotal;

            $discount = $coupon->calculateDiscount(
                $subtotal
            );

            $total = max(
                $subtotal - $discount,
                0
            );

            $order->update([
                'discount' => round(
                    $discount,
                    2
                ),
                'total' => round(
                    $total,
                    2
                ),
            ]);

            $usage = CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => round(
                    $discount,
                    2
                ),
                'coupon_code' => $coupon->code,
            ]);

            $coupon->increment(
                'usage_count'
            );

            return [
                'coupon' => $coupon->fresh(),
                'usage' => $usage->fresh(),
                'discount' => round(
                    $discount,
                    2
                ),
                'subtotal' => round(
                    $subtotal,
                    2
                ),
                'total' => round(
                    $total,
                    2
                ),
            ];
        });
    }
}