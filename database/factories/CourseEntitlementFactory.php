<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseEntitlement>
 */
class CourseEntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),

            'user_id' => User::factory(),

            'course_id' => Course::factory(),

            'source' => 'purchase',

            'can_access' => true,

            'status' => 'active',

            'granted_at' => now(),

            'expires_at' => null,

            'revoked_at' => null,
        ];
    }

    /**
     * Create an expired entitlement.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'can_access' => true,
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);
    }

    /**
     * Create a revoked entitlement.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Create an inactive entitlement.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'can_access' => true,
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }

    /**
     * Create an entitlement without course access.
     */
    public function withoutAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'can_access' => false,
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }

    /**
     * Create a free/granted entitlement.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'free',
            'can_access' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }

    /**
     * Create an entitlement granted by an administrator.
     */
    public function adminGrant(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'admin',
            'can_access' => true,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }
}