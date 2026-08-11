<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest users cannot view reviews.
     */
    public function test_guest_cannot_view_reviews(): void
    {
        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/reviews"
        );

        $response->assertUnauthorized();
    }

    /**
     * An entitled user can view reviews for a book.
     */
    public function test_entitled_user_can_view_reviews(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'review' => 'Excellent book.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/reviews"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.review', 'Excellent book.');
    }

    /**
     * A user without entitlement cannot access reviews.
     */
    public function test_user_cannot_access_reviews_for_unowned_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/reviews"
        );

        $response->assertForbidden();
    }

    /**
     * An entitled user can create a review.
     */
    public function test_entitled_user_can_create_review(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/reviews",
            [
                'rating' => 5,
                'review' => 'This book has been a blessing to me.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath(
                'data.review',
                'This book has been a blessing to me.'
            );

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'review' => 'This book has been a blessing to me.',
        ]);
    }

    /**
     * Review rating must be between 1 and 5.
     */
    public function test_review_rating_is_validated(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/reviews",
            [
                'rating' => 6,
                'review' => 'Invalid rating.',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rating',
            ]);
    }

    /**
     * Review text is optional.
     */
    public function test_review_text_is_optional(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/reviews",
            [
                'rating' => 4,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.review', null);
    }

    /**
     * A user cannot submit more than one review for the same book.
     */
    public function test_user_cannot_review_the_same_book_twice(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'review' => 'First review.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/reviews",
            [
                'rating' => 4,
                'review' => 'Second review.',
            ]
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 1);
    }

    /**
     * A user can update their own review.
     */
    public function test_user_can_update_their_own_review(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'review' => 'Original review.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/reviews/{$review->id}",
            [
                'rating' => 5,
                'review' => 'Updated review.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.review', 'Updated review.');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
            'review' => 'Updated review.',
        ]);
    }

    /**
     * A user cannot update another user's review.
     */
    public function test_user_cannot_update_another_users_review(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $review = Review::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'rating' => 4,
            'review' => 'Another user review.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/reviews/{$review->id}",
            [
                'rating' => 1,
                'review' => 'Unauthorized update.',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'review' => 'Another user review.',
        ]);
    }

    /**
     * A user can delete their own review.
     */
    public function test_user_can_delete_their_own_review(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(
            "/api/books/{$book->uuid}/reviews/{$review->id}"
        )->assertNoContent();

        $this->assertSoftDeleted('reviews', [
            'id' => $review->id,
        ]);
    }

    /**
     * A user cannot delete another user's review.
     */
    public function test_user_cannot_delete_another_users_review(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $review = Review::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/reviews/{$review->id}"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
        ]);
    }
}