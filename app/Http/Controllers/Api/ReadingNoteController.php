<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ReadingNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReadingNoteController extends Controller
{
    /**
     * Return the authenticated user's reading notes for a book.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findAccessibleBook($request, $uuid);

        $notes = ReadingNote::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->orderBy('current_page')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $notes,
        ]);
    }

    /**
     * Create a reading note.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $book = $this->findAccessibleBook($request, $uuid);

        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string'],
        ]);

        $note = ReadingNote::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'current_page' => $validated['current_page'],
            'location' => $validated['location'],
            'note' => $validated['note'],
        ]);

        return response()->json([
            'data' => $note,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update one of the authenticated user's reading notes.
     */
    public function update(
        Request $request,
        string $uuid,
        ReadingNote $readingNote
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureOwnedNote(
            $request,
            $book,
            $readingNote
        );

        $validated = $request->validate([
            'current_page' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string'],
        ]);

        $readingNote->update($validated);

        return response()->json([
            'data' => $readingNote->fresh(),
        ]);
    }

    /**
     * Delete one of the authenticated user's reading notes.
     */
    public function destroy(
        Request $request,
        string $uuid,
        ReadingNote $readingNote
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureOwnedNote(
            $request,
            $book,
            $readingNote
        );

        $readingNote->delete();

        return response()->noContent();
    }

    /**
     * Find a published book the authenticated user is entitled to access.
     */
    private function findAccessibleBook(Request $request, string $uuid): Book
    {
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

    /**
     * Ensure the note belongs to the authenticated user and requested book.
     */
    private function ensureOwnedNote(
        Request $request,
        Book $book,
        ReadingNote $readingNote
    ): void {
        abort_unless(
            $readingNote->user_id === $request->user()->id
            && $readingNote->book_id === $book->id,
            Response::HTTP_FORBIDDEN,
            'You do not have permission to modify this reading note.'
        );
    }
}