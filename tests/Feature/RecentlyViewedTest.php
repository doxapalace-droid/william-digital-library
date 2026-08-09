<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\RecentlyViewed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentlyViewedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot access recently viewed books.
     */
    public function test_guest_cannot_view_recently_viewed_books(): void
    {
        $response = $this->getJson('/api/recently-viewed');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated user can add a recently viewed book.
     */
    public function test_user_can_add_recently_viewed_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id'        => $user->id,
            'book_id'        => $book->id,
            'status'         => 'active',
            'can_read'       => true,
            'can_download'   => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/books/{$book->uuid}/recently-viewed");

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Book added to recently viewed.',
            ]);

        $this->assertDatabaseHas('recently_viewed', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * User can retrieve recently viewed books.
     */
    public function test_user_can_view_recently_viewed_books(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        RecentlyViewed::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/recently-viewed');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Viewing the same book twice should not create duplicates.
     */
    public function test_duplicate_views_update_existing_record(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id'        => $user->id,
            'book_id'        => $book->id,
            'status'         => 'active',
            'can_read'       => true,
            'can_download'   => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/books/{$book->uuid}/recently-viewed")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/books/{$book->uuid}/recently-viewed")
            ->assertOk();

        $this->assertEquals(
            1,
            RecentlyViewed::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->count()
        );
    }

    /**
     * Users cannot add books they are not entitled to.
     */
    public function test_user_cannot_add_book_without_entitlement(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/books/{$book->uuid}/recently-viewed")
            ->assertForbidden();
    }

    /**
     * Users only see their own recently viewed books.
     */
    public function test_users_only_see_their_own_recently_viewed_books(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $bookOne = Book::factory()->create([
            'is_published' => true,
        ]);

        $bookTwo = Book::factory()->create([
            'is_published' => true,
        ]);

        RecentlyViewed::factory()->create([
            'user_id' => $userOne->id,
            'book_id' => $bookOne->id,
        ]);

        RecentlyViewed::factory()->create([
            'user_id' => $userTwo->id,
            'book_id' => $bookTwo->id,
        ]);

        $response = $this->actingAs($userOne, 'sanctum')
            ->getJson('/api/recently-viewed');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}