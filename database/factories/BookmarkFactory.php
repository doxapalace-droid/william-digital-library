<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),

            'book_id' => Book::factory(),

            'current_page' => fake()->numberBetween(1, 300),

            'location' => 'page-' . fake()->numberBetween(1, 300),

            'label' => fake()->optional()->words(3, true),

            'note' => fake()->optional()->sentence(),
        ];
    }
}