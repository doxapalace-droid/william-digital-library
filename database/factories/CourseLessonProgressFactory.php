<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseLessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLessonProgress>
 */
class CourseLessonProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'position_seconds' => fake()->numberBetween(0, 3600),
            'completed' => false,
            'completed_at' => null,
            'last_accessed_at' => now(),
        ];
    }

    /**
     * Create a completed progress record.
     */
    public function completed(): static
    {
        return $this->state(function () {
            $completedAt = now()->subMinutes(
                fake()->numberBetween(1, 120)
            );

            return [
                'position_seconds' => fake()->numberBetween(
                    1,
                    3600
                ),
                'completed' => true,
                'completed_at' => $completedAt,
                'last_accessed_at' => $completedAt,
            ];
        });
    }

    /**
     * Create an incomplete progress record.
     */
    public function incomplete(): static
    {
        return $this->state([
            'completed' => false,
            'completed_at' => null,
        ]);
    }

    /**
     * Create progress at a specific playback position.
     */
    public function atPosition(int $seconds): static
    {
        return $this->state([
            'position_seconds' => $seconds,
            'completed' => false,
            'completed_at' => null,
        ]);
    }
}