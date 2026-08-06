<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Highlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HighlightController extends Controller
{
    /**
     * Return the authenticated user's highlights for a book.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findAccessibleBook($request, $uuid);

        $highlights = Highlight::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->orderBy('current_page')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $highlights,
        ]);
    }

    /**
     * Create a highlight.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findAccessibleBook($request, $uuid);

        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:1000'],
            'selected_text' => ['required', 'string'],
            'note' => ['nullable', 'string'],
            'color' => ['required', 'string', 'max:50'],
        ]);

        $highlight = Highlight::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            ...$validated,
        ]);

        return response()->json([
            'data' => $highlight,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update one of the authenticated user's highlights.
     */
    public function update(
        Request $request,
        string $uuid,
        Highlight $highlight
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureHighlightBelongsToBook($highlight, $book);

        abort_unless(
            $highlight->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN
        );

        $validated = $request->validate([
            'current_page' => ['sometimes', 'integer', 'min:1'],
            'location' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'selected_text' => ['sometimes', 'string'],
            'note' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'string', 'max:50'],
        ]);

        $highlight->update($validated);

        return response()->json([
            'data' => $highlight->fresh(),
        ]);
    }

    /**
     * Delete one of the authenticated user's highlights.
     */
    public function destroy(
        Request $request,
        string $uuid,
        Highlight $highlight
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureHighlightBelongsToBook($highlight, $book);

        abort_unless(
            $highlight->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN
        );

        $highlight->delete();

        return response()->noContent();
    }

    /**
     * Find a published book and ensure the user
     * currently has permission to read it.
     */
    private function findAccessibleBook(Request $request, string $uuid): Book
    {
        $book = Book::query()
            ->where('uuid', $uuid)
            ->where('is_published', true)
            ->firstOrFail();

        $hasAccess = $book->entitlements()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless($hasAccess, Response::HTTP_FORBIDDEN);

        return $book;
    }

    /**
     * Ensure the highlight belongs to the book in the URL.
     */
    private function ensureHighlightBelongsToBook(
        Highlight $highlight,
        Book $book
    ): void {
        abort_unless(
            $highlight->book_id === $book->id,
            Response::HTTP_NOT_FOUND
        );
    }
}