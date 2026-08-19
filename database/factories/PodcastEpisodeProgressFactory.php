<?php

namespace Database\Factories;

use App\Models\PodcastEpisode;
use App\Models\PodcastEpisodeProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PodcastEpisodeProgress>
 */
class PodcastEpisodeProgressFactory extends Factory
{
    protected $model = PodcastEpisodeProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $duration = fake()->numberBetween(
            60,
            3600
        );

        $position = fake()->numberBetween(
            0,
            $duration
        );

        $progressPercent = $duration > 0
            ? round(
                ($position / $duration) * 100,
                2
            )
            : 0;

        $progressPercent = min(
            100,
            max(0, $progressPercent)
        );

        $isCompleted = (
            $duration > 0
            && $position >= $duration
        );

        return [
            'user_id' => User::factory(),

            'podcast_episode_id' => PodcastEpisode::factory(),

            'position_seconds' => $position,

            'duration_seconds' => $duration,

            'progress_percent' => $progressPercent,

            'is_completed' => $isCompleted,

            'last_played_at' => $position > 0
                ? now()
                : null,
        ];
    }

    /**
     * Create incomplete progress.
     */
    public function incomplete(): static
    {
        return $this->state(
            function (): array {
                $duration = fake()->numberBetween(
                    60,
                    3600
                );

                $position = fake()->numberBetween(
                    1,
                    max(1, $duration - 1)
                );

                $percentage = round(
                    ($position / $duration) * 100,
                    2
                );

                return [
                    'position_seconds' => $position,
                    'duration_seconds' => $duration,
                    'progress_percent' => min(
                        100,
                        $percentage
                    ),
                    'is_completed' => false,
                    'last_played_at' => now(),
                ];
            }
        );
    }

    /**
     * Create completed progress.
     */
    public function completed(): static
    {
        return $this->state(
            function (): array {
                $duration = fake()->numberBetween(
                    60,
                    3600
                );

                return [
                    'position_seconds' => $duration,
                    'duration_seconds' => $duration,
                    'progress_percent' => 100,
                    'is_completed' => true,
                    'last_played_at' => now(),
                ];
            }
        );
    }

    /**
     * Create progress that has not started.
     */
    public function notStarted(): static
    {
        return $this->state(
            function (): array {
                $duration = fake()->numberBetween(
                    60,
                    3600
                );

                return [
                    'position_seconds' => 0,
                    'duration_seconds' => $duration,
                    'progress_percent' => 0,
                    'is_completed' => false,
                    'last_played_at' => null,
                ];
            }
        );
    }
}