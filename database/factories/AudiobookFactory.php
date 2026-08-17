<?php

namespace Database\Factories;

use App\Models\Audiobook;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audiobook>
 */
class AudiobookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),

            'description' => fake()->paragraph(),

            'cover_image' => null,

            'price' => fake()->randomFloat(
                2,
                5,
                100
            ),

            'currency' => 'USD',

            'status' => 'active',

            'duration_seconds' => fake()->numberBetween(
                600,
                7200
            ),

            'published_at' => now()->subDay(),
        ];
    }
}