<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot view bookmarks.
     */
    public function test_guest_cannot_view_bookmarks(): void
    {
        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/bookmarks"
        );

        $response->assertUnauthorized();
    }

    /**
     * An entitled customer can create a bookmark.
     */
    public function test_entitled_user_can_create_bookmark(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/bookmarks",
            [
                'current_page' => 25,
                'location' => 'page-25',
                'label' => 'Important section',
                'note' => 'Return to this section later.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.current_page', 25)
            ->assertJsonPath('data.location', 'page-25')
            ->assertJsonPath('data.label', 'Important section')
            ->assertJsonPath('data.note', 'Return to this section later.');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'location' => 'page-25',
            'label' => 'Important section',
        ]);
    }

    /**
     * An entitled customer can view their bookmarks.
     */
    public function test_entitled_user_can_view_their_bookmarks(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Bookmark::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 37,
            'location' => 'page-37',
            'label' => 'Key thought',
            'note' => 'Study this again.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/bookmarks"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.current_page', 37)
            ->assertJsonPath('data.0.label', 'Key thought');
    }

    /**
     * A customer only sees their own bookmarks.
     */
    public function test_user_cannot_see_another_users_bookmarks(): void
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
            'expires_at' => null,
        ]);

        Bookmark::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 50,
            'location' => 'page-50',
            'label' => 'Private bookmark',
            'note' => 'This belongs to another user.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/bookmarks"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A customer cannot create or view bookmarks
     * for a book they are not entitled to read.
     */
    public function test_user_cannot_access_bookmarks_for_unowned_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/bookmarks"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/bookmarks",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'label' => 'Test bookmark',
            ]
        )->assertForbidden();
    }

    /**
     * Bookmark input must be valid.
     */
    public function test_bookmark_is_validated(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/bookmarks",
            [
                'current_page' => 0,
                'label' => str_repeat('a', 256),
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_page',
                'label',
            ]);
    }

    /**
     * A customer can delete their own bookmark.
     */
    public function test_user_can_delete_their_own_bookmark(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        $bookmark = Bookmark::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 42,
            'location' => 'page-42',
            'label' => 'Remove me',
            'note' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/bookmarks/{$bookmark->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('bookmarks', [
            'id' => $bookmark->id,
        ]);
    }

    /**
     * A customer cannot delete another customer's bookmark.
     */
    public function test_user_cannot_delete_another_users_bookmark(): void
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
            'expires_at' => null,
        ]);

        $bookmark = Bookmark::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 60,
            'location' => 'page-60',
            'label' => 'Other user bookmark',
            'note' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/bookmarks/{$bookmark->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('bookmarks', [
            'id' => $bookmark->id,
            'user_id' => $otherUser->id,
        ]);
    }
}