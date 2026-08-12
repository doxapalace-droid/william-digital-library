<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReadingProgressController extends Controller
{
    /**
     * Return the authenticated user's reading progress
     * for a book they are entitled to read.
     */
    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $progress = ReadingProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->first();

        return response()->json([
            'data' => $progress
                ? [
                    'current_page' => $progress->current_page,
                    'total_pages' => $progress->total_pages,
                    'progress_percentage' => $progress->progress_percentage,
                    'last_read_at' => $progress->last_read_at,
                ]
                : null,
        ]);
    }

    /**
     * Create or update the authenticated user's
     * reading progress for a book.
     */
    public function update(
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

            'total_pages' => [
                'required',
                'integer',
                'min:1',
            ],

            'progress_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);

        /*
         * A reader cannot be positioned beyond
         * the final page of the book.
         */
        if ($validated['current_page'] > $validated['total_pages']) {
            return response()->json([
                'message' => 'The current page may not exceed the total pages.',
                'errors' => [
                    'current_page' => [
                        'The current page may not exceed the total pages.',
                    ],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $progress = ReadingProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
            ],
            [
                'current_page' => $validated['current_page'],
                'total_pages' => $validated['total_pages'],
                'progress_percentage' => $validated['progress_percentage'],
                'last_read_at' => now(),
            ]
        );

        return response()->json([
            'data' => [
                'current_page' => $progress->current_page,
                'total_pages' => $progress->total_pages,
                'progress_percentage' => $progress->progress_percentage,
                'last_read_at' => $progress->last_read_at,
            ],
        ]);
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