<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;
    use HasUuid;

    /*
     * Subscription statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'status',
        'amount',
        'currency',
        'starts_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'next_billing_at',
        'cancelled_at',
        'expires_at',
        'gateway',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_billing_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * Customer who owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Membership plan.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Determine whether the subscription is active.
     */
    public function isActive(): bool
    {
        if (
            $this->status !== self::STATUS_ACTIVE
        ) {
            return false;
        }

        return $this->expires_at === null
            || $this->expires_at->isFuture();
    }

    /**
     * Determine whether the subscription is trialing.
     */
    public function isTrialing(): bool
    {
        if (
            $this->status !== self::STATUS_TRIALING
        ) {
            return false;
        }

        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine whether the subscription is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Determine whether the subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Determine whether the subscription is expired.
     */
    public function isExpired(): bool
    {
        if (
            $this->status === self::STATUS_EXPIRED
        ) {
            return true;
        }

        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Determine whether the subscription grants
     * membership access.
     */
    public function grantsAccess(): bool
    {
        if (
            in_array(
                $this->status,
                [
                    self::STATUS_CANCELLED,
                    self::STATUS_EXPIRED,
                    self::STATUS_PENDING,
                ],
                true
            )
        ) {
            return false;
        }

        if (
            $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return false;
        }

        if (
            $this->status === self::STATUS_TRIALING
        ) {
            return $this->trial_ends_at !== null
                && $this->trial_ends_at->isFuture();
        }

        return in_array(
            $this->status,
            [
                self::STATUS_ACTIVE,
                self::STATUS_PAST_DUE,
            ],
            true
        );
    }

    /**
     * Determine whether the subscription can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_TRIALING,
                self::STATUS_ACTIVE,
                self::STATUS_PAST_DUE,
            ],
            true
        );
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}