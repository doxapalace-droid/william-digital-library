<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReadingNoteRequest;
use App\Http\Requests\UpdateReadingNoteRequest;
use App\Http\Resources\ReadingNoteCollection;
use App\Http\Resources\ReadingNoteResource;
use App\Models\Book;
use App\Models\ReadingNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReadingNoteController extends Controller
{
    /**
     * Display the authenticated user's reading notes for a book.
     */
    public function index(
        Request $request,
        string $uuid
    ): ReadingNoteCollection {
        $book = $this->findAccessibleBook($request, $uuid);

        $notes = ReadingNote::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->orderByRaw('current_page IS NULL')
            ->orderBy('current_page')
            ->orderBy('id')
            ->get();

        return new ReadingNoteCollection($notes);
    }

    /**
     * Store a new reading note.
     */
    public function store(
        StoreReadingNoteRequest $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $readingNote = ReadingNote::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Reading note created successfully.',
            'data' => new ReadingNoteResource($readingNote),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update one of the authenticated user's reading notes.
     */
    public function update(
        UpdateReadingNoteRequest $request,
        string $uuid,
        ReadingNote $readingNote
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureOwnedNote(
            $request,
            $book,
            $readingNote
        );

        $readingNote->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Reading note updated successfully.',
            'data' => new ReadingNoteResource(
                $readingNote->fresh()
            ),
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
     * Find a published book the authenticated user
     * currently has permission to access.
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

    /**
     * Ensure the reading note belongs to both the authenticated
     * user and the requested book.
     */
    private function ensureOwnedNote(
        Request $request,
        Book $book,
        ReadingNote $readingNote
    ): void {
        abort_unless(
            $readingNote->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
            'You do not have permission to modify this reading note.'
        );

        abort_unless(
            $readingNote->book_id === $book->id,
            Response::HTTP_NOT_FOUND,
            'Reading note not found.'
        );
    }
}