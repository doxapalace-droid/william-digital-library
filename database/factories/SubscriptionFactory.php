<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();

        $periodEnd = $startsAt->copy()->addMonth();

        return [
            'uuid' => (string) Str::uuid(),

            'user_id' => User::factory(),

            'membership_plan_id' => MembershipPlan::factory(),

            'status' => Subscription::STATUS_ACTIVE,

            'amount' => 19.99,

            'currency' => 'USD',

            'starts_at' => $startsAt,

            'trial_ends_at' => null,

            'current_period_start' => $startsAt,

            'current_period_end' => $periodEnd,

            'next_billing_at' => $periodEnd,

            'cancelled_at' => null,

            'expires_at' => null,

            'gateway' => 'paystack',

            'payment_reference' => 'SUB-' . strtoupper(
                Str::random(16)
            ),
        ];
    }
}