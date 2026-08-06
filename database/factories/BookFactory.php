<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 999999),
            'subtitle' => fake()->optional()->sentence(),
            'description' => fake()->paragraph(),
            'author' => 'William K. Danquah',
            'isbn' => null,
            'cover_image' => null,
            'ebook_file' => null,
            'pdf_path' => null,
            'price' => fake()->randomFloat(2, 1, 50),
            'currency' => 'USD',
            'is_featured' => false,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    /**
     * Mark the book as unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    /**
     * Mark the book as featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}