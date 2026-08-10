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
     * Returns books:
     * - owned by the authenticated user
     * - still entitled to read
     * - published
     * - not yet completed
     * - ordered by most recent reading activity
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $continueReading = ReadingProgress::query()

            /*
            |--------------------------------------------------------------------------
            | Eager Load Book
            |--------------------------------------------------------------------------
            */
            ->with('book')

            /*
            |--------------------------------------------------------------------------
            | Current User
            |--------------------------------------------------------------------------
            */
            ->where('user_id', $user->id)

            /*
            |--------------------------------------------------------------------------
            | Exclude Completed Books
            |--------------------------------------------------------------------------
            */
            ->where('progress_percentage', '<', 100)

            /*
            |--------------------------------------------------------------------------
            | Only Published Books
            |--------------------------------------------------------------------------
            */
            ->whereHas('book', function ($query) {
                $query->where('is_published', true);
            })

            /*
            |--------------------------------------------------------------------------
            | Verify Active Book Entitlement
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Most Recent First
            |--------------------------------------------------------------------------
            */
            ->orderByDesc('last_read_at')

            ->get();

        return response()->json([

            'data' => $continueReading->map(function (ReadingProgress $progress) {

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

            })->values(),

        ]);
    }
}