<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\ReadingNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingNote>
 */
class ReadingNoteFactory extends Factory
{
    protected $model = ReadingNote::class;

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

            'current_page' => fake()->numberBetween(1, 300),

            'location' => 'page-' . fake()->numberBetween(1, 300),

            'note' => fake()->paragraph(),
        ];
    }
}