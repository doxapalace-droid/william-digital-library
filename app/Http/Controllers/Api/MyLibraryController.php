<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyLibraryController extends Controller
{
    /**
     * Display the authenticated user's digital library.
     *
     * Only books for which the user has an active entitlement
     * are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $books = Book::query()
            ->select([
                'books.id',
                'books.uuid',
                'books.title',
                'books.slug',
                'books.subtitle',
                'books.description',
                'books.author',
                'books.cover_image',
                'books.is_published',
                'books.published_at',
            ])
            ->with([
                'categories:id,uuid,name,slug',
            ])
            ->where('books.is_published', true)
            ->whereHas('entitlements', function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where(function ($entitlementQuery) {
                        $entitlementQuery
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->orderByDesc('books.published_at')
            ->get();

        return response()->json([
            'data' => $books,
        ]);
    }
}