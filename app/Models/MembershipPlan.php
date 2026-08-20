<?php

namespace App\Models;

use App\Traits\HasUuid;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use HasFactory;
    use HasUuid;

    /**
     * Billing intervals.
     */
    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'billing_interval_count',
        'trial_days',
        'is_active',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval_count' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
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
     * Subscriptions using this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Determine whether the plan is currently active.
     */
    public function isActive(): bool
    {
        if (! $this->is_active || ! $this->is_published) {
            return false;
        }

        return $this->published_at === null
            || $this->published_at->isPast();
    }

    /**
     * Determine whether the plan can currently be purchased.
     */
    public function isPurchasable(): bool
    {
        return $this->isActive();
    }

    /**
     * Determine whether this is a free plan.
     */
    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /**
     * Determine whether this is a paid plan.
     */
    public function isPaid(): bool
    {
        return ! $this->isFree();
    }

    /**
     * Determine whether this is a monthly plan.
     */
    public function isMonthly(): bool
    {
        return $this->billing_interval === self::INTERVAL_MONTH;
    }

    /**
     * Determine whether this is a yearly plan.
     */
    public function isYearly(): bool
    {
        return $this->billing_interval === self::INTERVAL_YEAR;
    }

    /**
     * Determine whether this plan has a trial.
     */
    public function hasTrial(): bool
    {
        return $this->trial_days !== null
            && $this->trial_days > 0;
    }

    /**
     * Format the plan price.
     */
    public function formattedPrice(): string
    {
        return strtoupper($this->currency)
            . ' '
            . number_format(
                (float) $this->price,
                2
            );
    }
}