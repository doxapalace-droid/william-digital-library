<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Basic Membership',
            'Premium Membership',
            'Pro Membership',
            'Kingdom Membership',
            'Unlimited Membership',
        ]);

        return [
            'uuid' => (string) Str::uuid(),

            'name' => $name,

            'slug' => Str::slug($name),

            'description' => fake()->sentence(12),

            'price' => fake()->randomElement([
                0,
                9.99,
                19.99,
                29.99,
                49.99,
                99.99,
            ]),

            'currency' => 'USD',

            'billing_interval' => fake()->randomElement([
                'month',
                'year',
            ]),

            'billing_interval_count' => 1,

            'trial_days' => fake()->randomElement([
                null,
                7,
                14,
                30,
            ]),

            'is_active' => true,

            'is_published' => true,

            'published_at' => now(),
        ];
    }
}