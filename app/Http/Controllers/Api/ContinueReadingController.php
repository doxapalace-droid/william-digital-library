<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContinueReadingController extends Controller
{
    /**
     * Return books the authenticated user has started reading.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $progressRecords = ReadingProgress::query()
            ->with('book')
            ->where('user_id', $user->id)

            // Only include published books.
            ->whereHas('book', function ($query) {
                $query->where('is_published', true);
            })

            // User must still have a valid entitlement.
            ->whereHas('book.entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where(function ($query) {
                        $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })

            // Most recently read books first.
            ->orderByDesc('last_read_at')
            ->get();

        $books = $progressRecords->map(function (ReadingProgress $progress) {
            $book = $progress->book;

            return [
                'id' => $book->id,
                'uuid' => $book->uuid,
                'title' => $book->title,
                'slug' => $book->slug,
                'current_page' => $progress->current_page,
                'total_pages' => $progress->total_pages,
                'progress_percentage' => $progress->progress_percentage,
                'last_read_at' => $progress->last_read_at,
            ];
        })->values();

        return response()->json([
            'data' => $books,
        ]);
    }
}