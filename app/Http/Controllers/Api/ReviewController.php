<?php

namespace App\Http\Controllers\Api;


use App\Services\BookRatingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewCollection;
use App\Http\Resources\ReviewResource;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display all reviews for a book.
     */
    public function index(Book $book): JsonResponse
    {
        $reviews = $book->reviews()
            ->with('user:id,name')
            ->latest()
            ->get();

        return response()->json([
            'average_rating' => round(
                $book->reviews()->avg('rating') ?? 0,
                1
            ),

            'review_count' => $book->reviews()->count(),

            'data' => new ReviewCollection($reviews),
        ]);
    }

    /**
     * Store a new review.
     */
    public function store(StoreReviewRequest $request, Book $book): JsonResponse
    {
        $user = $request->user();

        // Ensure the user owns the book.
        $ownsBook = $user->bookEntitlements()
            ->where('book_id', $book->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless(
            $ownsBook,
            403,
            'You cannot review a book you do not own.'
        );

        // Prevent duplicate reviews.
        if (
            Review::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->exists()
        ) {
            return response()->json([
                'message' => 'You have already reviewed this book.',
            ], 422);
        }

        $data = $request->validated();

        $data['user_id'] = $user->id;
        $data['book_id'] = $book->id;

        $review = Review::create($data);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => new ReviewResource($review),
        ], 201);
    }

    /**
     * Update a review.
     */
    public function update(
        UpdateReviewRequest $request,
        Review $review
    ): JsonResponse {
        abort_if(
            $review->user_id !== $request->user()->id,
            403,
            'Unauthorized.'
        );

        $review->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Review updated successfully.',
            'data' => new ReviewResource($review->fresh()),
        ]);
    }

    /**
     * Delete a review.
     */
    public function destroy(
        Request $request,
        Review $review
    ): JsonResponse {
        abort_if(
            $review->user_id !== $request->user()->id,
            403,
            'Unauthorized.'
        );

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}