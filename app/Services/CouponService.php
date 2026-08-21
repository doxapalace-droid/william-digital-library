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
        $this->validateOrderOwnership(
            $user,
            $order
        );

        $coupon = Coupon::query()
            ->where(
                'code',
                Coupon::normalizeCode($code)
            )
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

        $subtotal = $this->getOrderSubtotal($order);

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
     * Validate that the order belongs to the customer.
     */
    private function validateOrderOwnership(
        User $user,
        Order $order
    ): void {
        if (
            (int) $order->user_id !==
            (int) $user->id
        ) {
            throw new RuntimeException(
                'You cannot apply a coupon to another customer\'s order.'
            );
        }
    }

    /**
     * Validate all coupon eligibility rules.
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

        $subtotal = $this->getOrderSubtotal($order);

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
     * Get the order subtotal as a normalized float.
     */
    private function getOrderSubtotal(
        Order $order
    ): float {
        $subtotal = round(
            (float) $order->subtotal,
            2
        );

        if ($subtotal < 0) {
            throw new RuntimeException(
                'The order subtotal cannot be negative.'
            );
        }

        return $subtotal;
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
            ->where(
                'coupon_id',
                $coupon->id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->count();

        return $usageCount >=
            $coupon->per_user_limit;
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
        /*
         * No restriction means the coupon applies
         * to the entire order.
         */
        if ($coupon->product_type === null) {
            return;
        }

        /*
         * Make sure the configured product type is
         * one of the product types supported by the
         * coupon system.
         *
         * This now includes bundles.
         */
        if (
            ! Coupon::isSupportedProductType(
                $coupon->product_type
            )
        ) {
            throw new RuntimeException(
                'This coupon has an invalid product restriction.'
            );
        }

        /*
         * The order must contain at least one item
         * matching the coupon's product restriction.
         */
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
        $this->validateOrderOwnership(
            $user,
            $order
        );

        return DB::transaction(
            function () use (
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
                    ->where(
                        'coupon_id',
                        $coupon->id
                    )
                    ->where(
                        'order_id',
                        $order->id
                    )
                    ->first();

                if ($existingUsage) {
                    return $existingUsage;
                }

                /*
                 * Lock the coupon while checking and updating
                 * usage limits.
                 */
                $lockedCoupon = Coupon::query()
                    ->whereKey($coupon->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Make sure the coupon is still valid at the
                 * exact moment the redemption is recorded.
                 */
                if (! $lockedCoupon->isCurrentlyValid()) {
                    throw new RuntimeException(
                        'This coupon is no longer valid.'
                    );
                }

                if (
                    $lockedCoupon->hasReachedUsageLimit()
                ) {
                    throw new RuntimeException(
                        'This coupon has reached its usage limit.'
                    );
                }

                /*
                 * Re-check the per-user usage limit while
                 * the coupon row is locked.
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

                /*
                 * Normalize and protect the discount amount.
                 */
                $discount = max(
                    0,
                    round($discount, 2)
                );

                $subtotal = $this->getOrderSubtotal(
                    $order
                );

                $discount = min(
                    $discount,
                    $subtotal
                );

                $usage = CouponUsage::create([
                    'coupon_id' => $lockedCoupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discount,
                    'coupon_code' => $lockedCoupon->code,
                ]);

                /*
                 * Increment the successful redemption count
                 * only after the usage record has been created.
                 */
                $lockedCoupon->increment(
                    'usage_count'
                );

                return $usage->fresh();
            }
        );
    }

    /**
     * Apply a coupon to an order and record its usage.
     *
     * This method validates the coupon, updates the order's
     * discount and total, and records the coupon usage in
     * one database transaction.
     *
     * This should normally be used only when the application
     * has decided that the coupon is actually being applied
     * to the order.
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
        $this->validateOrderOwnership(
            $user,
            $order
        );

        return DB::transaction(
            function () use (
                $code,
                $user,
                $order
            ) {
                /*
                 * Load order items because product-type
                 * restrictions depend on them.
                 */
                $order->loadMissing('items');

                /*
                 * Lock the coupon before checking its limits
                 * and applying it.
                 */
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

                /*
                 * Do not allow a second coupon to overwrite
                 * an existing discount silently.
                 */
                $existingUsage = CouponUsage::query()
                    ->where(
                        'order_id',
                        $order->id
                    )
                    ->first();

                if ($existingUsage) {
                    throw new RuntimeException(
                        'A coupon has already been applied to this order.'
                    );
                }

                /*
                 * Revalidate the coupon while the coupon row
                 * is locked.
                 */
                $this->validateCoupon(
                    $coupon,
                    $user,
                    $order
                );

                /*
                 * Always calculate the discount from the
                 * current order subtotal rather than trusting
                 * a value supplied by the client.
                 */
                $subtotal = $this->getOrderSubtotal(
                    $order
                );

                $discount = $coupon->calculateDiscount(
                    $subtotal
                );

                $discount = min(
                    max(
                        round($discount, 2),
                        0
                    ),
                    $subtotal
                );

                $total = max(
                    $subtotal - $discount,
                    0
                );

                /*
                 * Update the order using server-calculated
                 * values only.
                 */
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

                /*
                 * Record the successful redemption.
                 */
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

                /*
                 * Increase the successful redemption count.
                 */
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
            }
        );
    }
}