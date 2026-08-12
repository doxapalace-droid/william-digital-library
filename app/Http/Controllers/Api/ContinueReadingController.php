<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContinueReadingController extends Controller
{
    /**
     * Display the authenticated user's Continue Reading list.
     *
     * Only books that:
     * - belong to the authenticated user's reading progress
     * - are published
     * - have an active entitlement
     * - allow reading
     * - have not been revoked
     * - have not expired
     * - are not yet completed
     *
     * are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $continueReading = ReadingProgress::query()
            ->with([
                'book:id,uuid,title,slug,cover_image,author,is_published',
            ])

            // Only this user's reading progress.
            ->where('user_id', $user->id)

            // Completed books do not belong in Continue Reading.
            ->where('progress_percentage', '<', 100)

            // The book must still be published.
            ->whereHas('book', function ($query) {
                $query->where('is_published', true);
            })

            // The user must currently have permission to read the book.
            ->whereHas('book.entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('can_read', true)
                    ->whereNull('revoked_at')
                    ->where(function ($query) {
                        $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })

            // Most recently read books first.
            ->orderByDesc('last_read_at')

            ->get();

        return response()->json([
            'data' => $continueReading
                ->map(function (ReadingProgress $progress) {
                    $book = $progress->book;

                    return [
                        'id' => $book->id,
                        'uuid' => $book->uuid,
                        'title' => $book->title,
                        'slug' => $book->slug,
                        'cover_image' => $book->cover_image,
                        'author' => $book->author,

                        'current_page' => $progress->current_page,
                        'total_pages' => $progress->total_pages,
                        'progress_percentage' => $progress->progress_percentage,
                        'last_read_at' => $progress->last_read_at,
                    ];
                })
                ->values(),
        ]);
    }
}