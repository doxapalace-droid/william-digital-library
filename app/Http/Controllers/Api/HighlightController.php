<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHighlightRequest;
use App\Http\Requests\UpdateHighlightRequest;
use App\Http\Resources\HighlightCollection;
use App\Http\Resources\HighlightResource;
use App\Models\Book;
use App\Models\Highlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HighlightController extends Controller
{
    /**
     * Display the authenticated user's highlights for a book.
     */
    public function index(
        Request $request,
        string $uuid
    ): HighlightCollection {
        $book = $this->findAccessibleBook($request, $uuid);

        $highlights = Highlight::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->orderBy('current_page')
            ->orderBy('id')
            ->get();

        return new HighlightCollection($highlights);
    }

    /**
     * Store a newly created highlight.
     */
    public function store(
        StoreHighlightRequest $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $highlight = Highlight::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Highlight created successfully.',
            'data' => new HighlightResource($highlight),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the specified highlight.
     */
    public function update(
        UpdateHighlightRequest $request,
        string $uuid,
        Highlight $highlight
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureHighlightBelongsToBook(
            $highlight,
            $book
        );

        $this->ensureHighlightBelongsToUser(
            $request,
            $highlight
        );

        $highlight->update($request->validated());

        return response()->json([
            'message' => 'Highlight updated successfully.',
            'data' => new HighlightResource(
                $highlight->fresh()
            ),
        ]);
    }

    /**
     * Remove the specified highlight.
     */
    public function destroy(
        Request $request,
        string $uuid,
        Highlight $highlight
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureHighlightBelongsToBook(
            $highlight,
            $book
        );

        $this->ensureHighlightBelongsToUser(
            $request,
            $highlight
        );

        $highlight->delete();

        return response()->noContent();
    }

    /**
     * Find a published book that the authenticated user
     * currently has permission to read.
     *
     * A valid reading entitlement must:
     *
     * - belong to the authenticated user
     * - be active
     * - allow reading
     * - not be revoked
     * - not be expired
     */
    private function findAccessibleBook(
        Request $request,
        string $uuid
    ): Book {
        $book = Book::query()
            ->where('uuid', $uuid)
            ->where('is_published', true)
            ->firstOrFail();

        $hasAccess = $book->entitlements()
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
            $hasAccess,
            Response::HTTP_FORBIDDEN,
            'You do not have access to this book.'
        );

        return $book;
    }

    /**
     * Ensure the highlight belongs to the requested book.
     */
    private function ensureHighlightBelongsToBook(
        Highlight $highlight,
        Book $book
    ): void {
        abort_unless(
            $highlight->book_id === $book->id,
            Response::HTTP_NOT_FOUND,
            'Highlight not found.'
        );
    }

    /**
     * Ensure the highlight belongs to the authenticated user.
     */
    private function ensureHighlightBelongsToUser(
        Request $request,
        Highlight $highlight
    ): void {
        abort_unless(
            $highlight->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
            'You do not have permission to modify this highlight.'
        );
    }
}