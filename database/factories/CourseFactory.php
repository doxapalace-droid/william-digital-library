<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(
            fake()->numberBetween(2, 5)
        );

        return [
            'uuid' => (string) Str::uuid(),

            'title' => $title,

            'slug' => Str::slug($title),

            'subtitle' => fake()->sentence(),

            'description' => fake()->paragraphs(
                2,
                true
            ),

            'cover_image' => 'courses/covers/' .
                fake()->uuid() .
                '.jpg',

            'price' => fake()->randomFloat(
                2,
                0,
                200
            ),

            'currency' => 'USD',

            'status' => 'active',

            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Create a draft course.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Create an inactive course.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * Create a course scheduled for future publication.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Create a free course.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
        ]);
    }
}