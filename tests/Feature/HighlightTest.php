<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HighlightTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest users cannot view highlights.
     */
    public function test_guest_cannot_view_highlights(): void
    {
        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->getJson(
            "/api/books/{$book->uuid}/highlights"
        )->assertUnauthorized();
    }

    /**
     * An entitled user can create a highlight.
     */
    public function test_entitled_user_can_create_highlight(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/highlights",
            [
                'current_page' => 25,
                'location' => 'page-25-selection-1',
                'selected_text' => 'Faith comes by hearing.',
                'note' => 'Important teaching point.',
                'color' => 'yellow',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.current_page', 25)
            ->assertJsonPath(
                'data.selected_text',
                'Faith comes by hearing.'
            )
            ->assertJsonPath(
                'data.note',
                'Important teaching point.'
            )
            ->assertJsonPath('data.color', 'yellow');

        $this->assertDatabaseHas('highlights', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'selected_text' => 'Faith comes by hearing.',
            'color' => 'yellow',
        ]);
    }

    /**
     * An entitled user can retrieve their highlights.
     */
    public function test_entitled_user_can_view_their_highlights(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        Highlight::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 37,
            'location' => 'page-37-selection-1',
            'selected_text' => 'The just shall live by faith.',
            'note' => 'Study this later.',
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/highlights"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.selected_text',
                'The just shall live by faith.'
            )
            ->assertJsonPath('data.0.current_page', 37);
    }

    /**
     * A user cannot see highlights belonging to another user.
     */
    public function test_user_cannot_see_another_users_highlights(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        Highlight::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 10,
            'selected_text' => 'Private highlight.',
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/highlights"
        );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A user cannot access highlights for a book
     * they are not entitled to read.
     */
    public function test_user_cannot_access_highlights_for_unowned_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/highlights"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/highlights",
            [
                'current_page' => 5,
                'selected_text' => 'Test highlight.',
                'color' => 'yellow',
            ]
        )->assertForbidden();
    }

    /**
     * Highlight input must be valid.
     */
    public function test_highlight_is_validated(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/highlights",
            [
                'current_page' => 0,
                'selected_text' => '',
                'color' => '',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_page',
                'selected_text',
                'color',
            ]);
    }

    /**
     * A user can update their own highlight.
     */
    public function test_user_can_update_their_own_highlight(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        $highlight = Highlight::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 20,
            'selected_text' => 'Original highlight.',
            'note' => null,
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/highlights/{$highlight->id}",
            [
                'note' => 'Updated personal note.',
                'color' => 'green',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.note',
                'Updated personal note.'
            )
            ->assertJsonPath('data.color', 'green');

        $this->assertDatabaseHas('highlights', [
            'id' => $highlight->id,
            'user_id' => $user->id,
            'note' => 'Updated personal note.',
            'color' => 'green',
        ]);
    }

    /**
     * A user cannot update another user's highlight.
     */
    public function test_user_cannot_update_another_users_highlight(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        $highlight = Highlight::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 15,
            'selected_text' => 'Someone else owns this.',
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $this->putJson(
            "/api/books/{$book->uuid}/highlights/{$highlight->id}",
            [
                'note' => 'Trying to change it.',
                'color' => 'green',
            ]
        )->assertForbidden();
    }

    /**
     * A user can delete their own highlight.
     */
    public function test_user_can_delete_their_own_highlight(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        $highlight = Highlight::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 50,
            'selected_text' => 'Delete this highlight.',
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(
            "/api/books/{$book->uuid}/highlights/{$highlight->id}"
        )->assertNoContent();

        $this->assertDatabaseMissing('highlights', [
            'id' => $highlight->id,
        ]);
    }

    /**
     * A user cannot delete another user's highlight.
     */
    public function test_user_cannot_delete_another_users_highlight(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        $highlight = Highlight::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 60,
            'selected_text' => 'Protected highlight.',
            'color' => 'yellow',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(
            "/api/books/{$book->uuid}/highlights/{$highlight->id}"
        )->assertForbidden();

        $this->assertDatabaseHas('highlights', [
            'id' => $highlight->id,
        ]);
    }

    /**
     * Give a user permission to read a book.
     */
    private function entitleUser(User $user, Book $book): void
    {
        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);
    }
}