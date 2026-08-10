<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Highlight>
 */
class HighlightFactory extends Factory
{
    protected $model = Highlight::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),

            'book_id' => Book::factory(),

            'current_page' => fake()->numberBetween(1, 500),

            'location' => 'page-' . fake()->numberBetween(1, 500),

            'selected_text' => fake()->paragraph(),

            'note' => fake()->optional()->sentence(),

            'color' => fake()->randomElement([
                'yellow',
                'green',
                'blue',
                'pink',
                'orange',
            ]),
        ];
    }
}