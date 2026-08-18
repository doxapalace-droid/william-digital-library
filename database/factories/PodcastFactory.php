<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PodcastFactory extends Factory
{
    /**
     * The model associated with the factory.
     */
    protected $model = \App\Models\Podcast::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,

            'slug' => Str::slug($title),

            'description' =>
                fake()->paragraph(),

            'cover_image' =>
                'podcasts/covers/default.jpg',

            'status' => 'active',

            'is_featured' => false,

            'published_at' =>
                now()->subDay(),
        ];
    }
}