<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Bookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookmarkController extends Controller
{
    /**
     * Return the authenticated user's bookmarks
     * for a book they are entitled to read.
     */
    public function index(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $bookmarks = Bookmark::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->orderBy('current_page')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $bookmarks->map(
                function (Bookmark $bookmark) {
                    return [
                        'id' => $bookmark->id,
                        'current_page' => $bookmark->current_page,
                        'location' => $bookmark->location,
                        'label' => $bookmark->label,
                        'note' => $bookmark->note,
                        'created_at' => $bookmark->created_at,
                        'updated_at' => $bookmark->updated_at,
                    ];
                }
            )->values(),
        ]);
    }

    /**
     * Create a bookmark for the authenticated user.
     */
    public function store(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $validated = $request->validate([
            'current_page' => [
                'required',
                'integer',
                'min:1',
            ],
            'location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'label' => [
                'nullable',
                'string',
                'max:255',
            ],
            'note' => [
                'nullable',
                'string',
            ],
        ]);

        $bookmark = Bookmark::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'current_page' => $validated['current_page'],
            'location' => $validated['location'] ?? null,
            'label' => $validated['label'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $bookmark->id,
                'current_page' => $bookmark->current_page,
                'location' => $bookmark->location,
                'label' => $bookmark->label,
                'note' => $bookmark->note,
                'created_at' => $bookmark->created_at,
                'updated_at' => $bookmark->updated_at,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete one of the authenticated user's bookmarks.
     */
    public function destroy(
        Request $request,
        string $uuid,
        int $bookmark
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        $bookmarkRecord = Bookmark::query()
            ->where('id', $bookmark)
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->firstOrFail();

        $bookmarkRecord->delete();

        return response()->noContent();
    }

    /**
     * Find a published book that the authenticated
     * user currently has permission to read.
     */
    private function findAccessibleBook(
        Request $request,
        string $uuid
    ): Book {
        $book = Book::query()
            ->where('uuid', $uuid)
            ->where('is_published', true)
            ->firstOrFail();

        $hasEntitlement = $book->entitlements()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('can_read', true)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless(
            $hasEntitlement,
            Response::HTTP_FORBIDDEN,
            'You do not have access to this book.'
        );

        return $book;
    }
}