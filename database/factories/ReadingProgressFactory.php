<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingProgress>
 */
class ReadingProgressFactory extends Factory
{
    protected $model = ReadingProgress::class;

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

            'current_page' => 1,
            'total_pages' => 100,
            'location' => null,
            'progress_percentage' => 1,
            'last_read_at' => now(),
        ];
    }
}