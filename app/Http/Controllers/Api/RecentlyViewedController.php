<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\RecentlyViewed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentlyViewedController extends Controller
{
    /**
     * Display the authenticated user's recently viewed books.
     */
    public function index(Request $request): JsonResponse
    {
        $recentlyViewed = RecentlyViewed::query()
            ->with('book')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_viewed_at')
            ->get();

        return response()->json([
            'data' => $recentlyViewed,
        ]);
    }

    /**
     * Store or update a recently viewed book.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $book = Book::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Verify entitlement
        |--------------------------------------------------------------------------
        |
        | Only customers who own the book should be able to add it to
        | their Recently Viewed list.
        |
        */

        abort_unless(
            $request->user()
                ->bookEntitlements()
                ->where('book_id', $book->id)
                ->exists(),
            403
        );

        RecentlyViewed::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
            ],
            [
                'last_viewed_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Book added to recently viewed.',
        ]);
    }
}