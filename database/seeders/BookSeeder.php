<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | I Am Born Again
        |--------------------------------------------------------------------------
        |
        | pdf_path is relative to the "books" filesystem disk:
        |
        | storage/app/private/books/iam-born-again.pdf
        |
        */

        $book = Book::where('slug', 'i-am-born-again')->first();

        if ($book) {
            $book->update([
                'title' => 'I Am Born Again',
                'subtitle' => null,
                'description' => 'A digital book by William K. Danquah.',
                'author' => 'William K. Danquah',
                'price' => 6.99,
                'currency' => 'USD',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => $book->published_at ?? now(),
                'pdf_path' => 'iam-born-again.pdf',
            ]);

            return;
        }

        Book::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'I Am Born Again',
            'slug' => 'i-am-born-again',
            'subtitle' => null,
            'description' => 'A digital book by William K. Danquah.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_featured' => true,
            'is_published' => true,
            'published_at' => now(),
            'pdf_path' => 'iam-born-again.pdf',
        ]);
    }
}