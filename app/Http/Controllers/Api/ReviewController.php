<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewCollection;
use App\Http\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use App\Services\BookRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReviewController extends Controller
{
    /**
     * Display all reviews for an accessible published book.
     */
    public function index(
        Request $request,
        string $uuid
    ): ReviewCollection {
        $book = $this->findAccessibleBook($request, $uuid);

        $reviews = $book->reviews()
            ->with('user:id,name')
            ->latest()
            ->get();

        return new ReviewCollection($reviews);
    }

    /**
     * Display rating statistics for an accessible published book.
     */
    public function statistics(
        Request $request,
        string $uuid,
        BookRatingService $ratingService
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        return response()->json([
            'data' => $ratingService->statistics($book),
        ]);
    }

    /**
     * Store a new review.
     */
    public function store(
        StoreReviewRequest $request,
        string $uuid
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $user = $request->user();

        /*
         * A user may only review a book once.
         */
        $alreadyReviewed = Review::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->exists();

        abort_if(
            $alreadyReviewed,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'You have already reviewed this book.'
        );

        $review = Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            ...$request->validated(),
        ]);

        /*
         * Load the reviewer for the API response.
         */
        $review->load('user:id,name');

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => new ReviewResource($review),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the authenticated user's own review.
     */
    public function update(
        UpdateReviewRequest $request,
        string $uuid,
        Review $review
    ): JsonResponse {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureReviewBelongsToBook($review, $book);

        abort_unless(
            $review->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
            'Unauthorized.'
        );

        $review->update($request->validated());

        /*
         * Load the reviewer for the API response.
         */
        $review->load('user:id,name');

        return response()->json([
            'message' => 'Review updated successfully.',
            'data' => new ReviewResource($review),
        ]);
    }

    /**
     * Delete the authenticated user's own review.
     */
    public function destroy(
        Request $request,
        string $uuid,
        Review $review
    ): Response {
        $book = $this->findAccessibleBook($request, $uuid);

        $this->ensureReviewBelongsToBook($review, $book);

        abort_unless(
            $review->user_id === $request->user()->id,
            Response::HTTP_FORBIDDEN,
            'Unauthorized.'
        );

        $review->delete();

        return response()->noContent();
    }

    /**
     * Find a published book the authenticated user can access.
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
            ->where('can_read', true)
            ->where('status', 'active')
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
     * Ensure the review belongs to the requested book.
     */
    private function ensureReviewBelongsToBook(
        Review $review,
        Book $book
    ): void {
        abort_unless(
            $review->book_id === $book->id,
            Response::HTTP_NOT_FOUND,
            'Review not found.'
        );
    }
}