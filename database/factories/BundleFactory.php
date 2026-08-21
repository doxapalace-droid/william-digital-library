<?php

namespace Database\Factories;

use App\Models\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    /**
     * The model associated with this factory.
     */
    protected $model = Bundle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(
            random_int(2, 4),
            true
        );

        return [
            'uuid' => (string) Str::uuid(),

            'name' => $name,

            'slug' => Str::slug($name)
                . '-'
                . fake()->unique()->numberBetween(1000, 9999),

            'description' => fake()->optional()->paragraph(),

            'cover_image' => null,

            'price' => fake()->randomFloat(
                2,
                5,
                200
            ),

            'currency' => 'USD',

            'is_active' => true,

            'is_published' => true,

            'published_at' => now(),
        ];
    }

    /**
     * Create a draft bundle.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Create an inactive bundle.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Create a future-published bundle.
     */
    public function future(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Create a free bundle.
     */
    public function free(): static
    {
        return $this->state(fn () => [
            'price' => 0,
        ]);
    }
}