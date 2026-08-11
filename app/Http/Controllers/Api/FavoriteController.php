<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FavoriteController extends Controller
{
    /**
     * Display whether the authenticated user's favorite
     * exists for the requested book.
     */
    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

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
     * Add the requested book to the authenticated user's favorites.
     */
    public function store(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $favorite = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->first();

        if ($favorite) {
            return response()->json([
                'data' => [
                    'is_favorite' => true,
                ],
            ]);
        }

        Favorite::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
        ]);

        return response()->json([
            'data' => [
                'is_favorite' => true,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove the requested book from the authenticated user's favorites.
     */
    public function destroy(
        Request $request,
        string $uuid
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }

    /**
     * Find a published book the authenticated user is entitled to access.
     */
    private function findAccessibleBook(
        Request $request,
        string $uuid
    ): Book {
        $book = Book::query()
            ->where('uuid', $uuid)
            ->where('is_published', true)
            ->firstOrFail();

        $hasAccess = $request->user()
            ->bookEntitlements()
            ->where('book_id', $book->id)
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless(
            $hasAccess,
            Response::HTTP_FORBIDDEN,
            'You do not have access to this book.'
        );

        return $book;
    }
}