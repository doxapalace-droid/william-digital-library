<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),

            'slug' => fake()
                ->unique()
                ->slug(),

            'description' => fake()->paragraph(),

            'cover_image' => null,

            'video_file' => null,

            'price' => fake()->randomFloat(
                2,
                5,
                100
            ),

            'currency' => 'USD',

            'status' => 'active',

            'duration_seconds' => fake()->numberBetween(
                300,
                7200
            ),

            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Create a draft video.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Create an inactive video.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * Create a future-published video.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Create a free video.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
        ]);
    }
}