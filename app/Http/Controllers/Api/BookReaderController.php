<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookReaderController extends Controller
{
    /**
     * Open a book for an authorised customer.
     */
    public function show(Request $request, Book $book): JsonResponse
    {
        $user = $request->user();

        if (! $user->canReadBook($book)) {
            return response()->json([
                'message' => 'You do not have access to this book.',
            ], 403);
        }

        return response()->json([
            'message' => 'Book access granted.',
            'book' => [
                'uuid' => $book->uuid,
                'title' => $book->title,
                'author' => $book->author,
            ],
        ]);
    }
}