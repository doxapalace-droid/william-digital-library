<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\RecentlyViewed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecentlyViewed>
 */
class RecentlyViewedFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\RecentlyViewed>
     */
    protected $model = RecentlyViewed::class;

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
            'last_viewed_at' => now(),
        ];
    }
}