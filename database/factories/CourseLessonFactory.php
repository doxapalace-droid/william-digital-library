<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
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

            'course_id' => Course::factory(),

            'video_id' => Video::factory(),

            'title' => $title,

            'slug' => Str::slug($title),

            'description' => fake()->paragraph(),

            'lesson_number' => fake()->unique()->numberBetween(
                1,
                100
            ),

            'status' => 'active',

            'is_preview' => false,

            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Create a draft lesson.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Create an inactive lesson.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * Create a lesson scheduled for future publication.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);
    }

    /**
     * Create a preview lesson.
     */
    public function preview(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preview' => true,
        ]);
    }

    /**
     * Create a lesson with a specific lesson number.
     */
    public function numbered(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'lesson_number' => $number,
        ]);
    }
}