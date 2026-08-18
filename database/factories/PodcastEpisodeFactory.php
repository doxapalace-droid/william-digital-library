<?php

namespace Database\Factories;

use App\Models\Podcast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PodcastEpisodeFactory extends Factory
{
    /**
     * The model associated with the factory.
     */
    protected $model = \App\Models\PodcastEpisode::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'podcast_id' =>
                Podcast::factory(),

            'title' => $title,

            'slug' =>
                Str::slug($title),

            'description' =>
                fake()->paragraph(),

            'cover_image' => null,

            'audio_file' =>
                'podcasts/audio/example.mp3',

            'video_file' =>
                null,

            'duration_seconds' =>
                fake()->numberBetween(
                    300,
                    3600
                ),

            'status' => 'active',

            'is_free' => true,

            'is_featured' => false,

            'episode_number' =>
                fake()->unique()->numberBetween(
                    1,
                    1000
                ),

            'published_at' =>
                now()->subDay(),
        ];
    }
}