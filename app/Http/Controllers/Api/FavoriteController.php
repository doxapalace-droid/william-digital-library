<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Check whether the authenticated user
     * has favorited this book.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findBook($uuid);

        if (! $this->hasAccess($request, $book)) {
            return response()->json([
                'message' => 'You do not have access to this book.',
            ], 403);
        }

        $isFavorite = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->exists();

        return response()->json([
            'data' => [
                'is_favorite' => $isFavorite,
            ],
        ]);
    }

    /**
     * Add the book to the authenticated
     * user's favorites.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findBook($uuid);

        if (! $this->hasAccess($request, $book)) {
            return response()->json([
                'message' => 'You do not have access to this book.',
            ], 403);
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $favorite->id,
                'book_id' => $favorite->book_id,
                'is_favorite' => true,
            ],
        ], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove the book from the authenticated
     * user's favorites.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findBook($uuid);

        if (! $this->hasAccess($request, $book)) {
            return response()->json([
                'message' => 'You do not have access to this book.',
            ], 403);
        }

        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->delete();

        return response()->json([
            'data' => [
                'is_favorite' => false,
            ],
        ]);
    }

    /**
     * Find a book by UUID.
     */
    private function findBook(string $uuid): Book
    {
        return Book::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Determine whether the authenticated user
     * currently has access to the book.
     */
    private function hasAccess(Request $request, Book $book): bool
    {
        return BookEntitlement::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}