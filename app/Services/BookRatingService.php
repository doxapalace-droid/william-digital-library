<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class BookRatingService
{
    /**
     * Refresh the rating statistics for a book.
     */
    public function refresh(Book $book): Book
    {
        $statistics = $book->reviews()
            ->selectRaw('
                COUNT(*) as reviews_count,
                COALESCE(AVG(rating), 0) as average_rating
            ')
            ->first();

        $book->forceFill([
            'average_rating' => round(
                (float) $statistics->average_rating,
                2
            ),
            'reviews_count' => (int) $statistics->reviews_count,
        ]);

        if ($book->isDirty([
            'average_rating',
            'reviews_count',
        ])) {
            $book->save();
        }

        return $book->refresh();
    }
}