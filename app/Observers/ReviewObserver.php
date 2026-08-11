<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\BookRatingService;

class ReviewObserver
{
    public function __construct(
        protected BookRatingService $ratingService
    ) {
    }

    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        $this->refreshBookRating($review);
    }

    /**
     * Handle the Review "updated" event.
     */
    public function updated(Review $review): void
    {
        $this->refreshBookRating($review);
    }

    /**
     * Handle the Review "deleted" event.
     */
    public function deleted(Review $review): void
    {
        $this->refreshBookRating($review);
    }

    /**
     * Handle the Review "restored" event.
     */
    public function restored(Review $review): void
    {
        $this->refreshBookRating($review);
    }

    /**
     * Handle the Review "force deleted" event.
     */
    public function forceDeleted(Review $review): void
    {
        $this->refreshBookRating($review);
    }

    /**
     * Refresh the rating statistics for the affected book.
     */
    protected function refreshBookRating(Review $review): void
    {
        $book = $review->book()
            ->withTrashed()
            ->first();

        if ($book) {
            $this->ratingService->refresh($book);
        }
    }
}