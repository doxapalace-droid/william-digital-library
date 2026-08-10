<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Collection;

class RecommendationService
{
    /**
     * Get recommended books for a user.
     */
    public function getRecommendations(User $user, int $limit = 10): Collection
    {
        // Books the user already owns.
        $ownedBookIds = $user->bookEntitlements()
            ->pluck('book_id');

        return Book::query()
            ->where('is_published', true)
            ->whereNotIn('id', $ownedBookIds)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}