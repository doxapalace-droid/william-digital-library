<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest users cannot view favorites.
     */
    public function test_guest_cannot_view_favorites(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response->assertUnauthorized();
    }

    /**
     * An entitled user can add a book to favorites.
     */
    public function test_entitled_user_can_add_book_to_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * An entitled user can view whether a book is favorited.
     */
    public function test_entitled_user_can_view_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);
    }

    /**
     * A user cannot access favorites for a book they do not own.
     */
    public function test_user_cannot_access_favorites_for_unowned_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response->assertForbidden();
    }

    /**
     * Adding the same book twice must not create duplicate favorites.
     */
    public function test_adding_favorite_twice_does_not_create_duplicate(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/books/{$book->uuid}/favorites"
        )->assertCreated();

        $this->postJson(
            "/api/books/{$book->uuid}/favorites"
        )->assertSuccessful();

        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * A user can remove their own favorite.
     */
    public function test_user_can_remove_their_favorite(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response->assertSuccessful();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * One user's favorite does not make the book
     * a favorite for another user.
     */
    public function test_favorites_are_private_to_each_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        BookEntitlement::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Favorite::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/favorites"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }
}