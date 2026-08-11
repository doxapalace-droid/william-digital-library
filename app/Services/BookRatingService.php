<?php

namespace App\Services;

use App\Models\Book;

class BookRatingService
{
    /**
     * Refresh the cached rating statistics for a book.
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

    /**
     * Get complete rating statistics for a book.
     */
    public function statistics(Book $book): array
    {
        $distribution = $book->reviews()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $total = (int) $book->reviews()->count();

        $ratings = [];

        foreach (range(5, 1) as $rating) {
            $count = (int) ($distribution[$rating] ?? 0);

            $ratings[$rating] = [
                'count' => $count,
                'percentage' => $total > 0
                    ? round(($count / $total) * 100, 2)
                    : 0,
            ];
        }

        return [
            'average_rating' => (float) $book->average_rating,
            'reviews_count' => (int) $book->reviews_count,
            'distribution' => $ratings,
        ];
    }
}