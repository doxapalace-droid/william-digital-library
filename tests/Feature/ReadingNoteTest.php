<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\ReadingNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadingNoteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot view reading notes.
     */
    public function test_guest_cannot_view_reading_notes(): void
    {
        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/notes"
        );

        $response->assertUnauthorized();
    }

    /**
     * An entitled user can create a reading note.
     */
    public function test_entitled_user_can_create_reading_note(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 12,
                'location' => 'chapter-2',
                'note' => 'This is an important Kingdom principle.',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.current_page',
                12
            )
            ->assertJsonPath(
                'data.location',
                'chapter-2'
            )
            ->assertJsonPath(
                'data.note',
                'This is an important Kingdom principle.'
            );

        $this->assertDatabaseHas('reading_notes', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 12,
            'location' => 'chapter-2',
            'note' => 'This is an important Kingdom principle.',
        ]);
    }

    /**
     * An entitled user can view their reading notes.
     */
    public function test_entitled_user_can_view_their_reading_notes(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        ReadingNote::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 8,
            'location' => 'chapter-1',
            'note' => 'Remember this point.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/notes"
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'current_page' => 8,
                'location' => 'chapter-1',
                'note' => 'Remember this point.',
            ]);
    }

    /**
     * A user can only see their own reading notes.
     */
    public function test_user_cannot_see_another_users_reading_notes(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        ReadingNote::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 15,
            'location' => 'chapter-3',
            'note' => 'Private note belonging to another user.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/notes"
        );

        $response
            ->assertOk()
            ->assertJsonMissing([
                'note' => 'Private note belonging to another user.',
            ]);
    }

    /**
     * A user without an entitlement cannot access reading notes.
     */
    public function test_user_cannot_access_reading_notes_for_unowned_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertForbidden();
    }

    /**
     * A user without read permission cannot access reading notes.
     */
    public function test_user_cannot_access_reading_notes_without_read_permission(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => false,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertForbidden();
    }

    /**
     * An inactive entitlement cannot access reading notes.
     */
    public function test_user_cannot_access_reading_notes_with_inactive_entitlement(): void
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
            'status' => 'inactive',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertForbidden();
    }

    /**
     * A revoked entitlement cannot access reading notes.
     */
    public function test_user_cannot_access_reading_notes_with_revoked_entitlement(): void
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
            'expires_at' => null,
            'revoked_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertForbidden();
    }

    /**
     * An expired entitlement cannot access reading notes.
     */
    public function test_user_cannot_access_reading_notes_with_expired_entitlement(): void
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
            'granted_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertForbidden();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertForbidden();
    }

    /**
     * Reading note input must be valid.
     */
    public function test_reading_note_is_validated(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 0,
                'location' => '',
                'note' => '',
            ]
        );

        $response->assertUnprocessable();
    }

    /**
     * A user can update their own reading note.
     */
    public function test_user_can_update_their_own_reading_note(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        $readingNote = ReadingNote::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 5,
            'location' => 'chapter-1',
            'note' => 'Original note.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/notes/{$readingNote->id}",
            [
                'current_page' => 6,
                'location' => 'chapter-1',
                'note' => 'Updated note.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.current_page',
                6
            )
            ->assertJsonPath(
                'data.note',
                'Updated note.'
            );

        $this->assertDatabaseHas('reading_notes', [
            'id' => $readingNote->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 6,
            'note' => 'Updated note.',
        ]);
    }

    /**
     * A user cannot update another user's reading note.
     */
    public function test_user_cannot_update_another_users_reading_note(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        $readingNote = ReadingNote::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 10,
            'location' => 'chapter-2',
            'note' => 'Another users private note.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/notes/{$readingNote->id}",
            [
                'current_page' => 11,
                'location' => 'chapter-2',
                'note' => 'Attempted change.',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('reading_notes', [
            'id' => $readingNote->id,
            'user_id' => $otherUser->id,
            'note' => 'Another users private note.',
        ]);
    }

    /**
     * A user can delete their own reading note.
     */
    public function test_user_can_delete_their_own_reading_note(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);

        $readingNote = ReadingNote::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 20,
            'location' => 'chapter-4',
            'note' => 'Delete this note.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/notes/{$readingNote->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('reading_notes', [
            'id' => $readingNote->id,
        ]);
    }

    /**
     * A user cannot delete another user's reading note.
     */
    public function test_user_cannot_delete_another_users_reading_note(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $this->entitleUser($user, $book);
        $this->entitleUser($otherUser, $book);

        $readingNote = ReadingNote::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'location' => 'chapter-5',
            'note' => 'Protected private note.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/books/{$book->uuid}/notes/{$readingNote->id}"
        );

        $response->assertForbidden();

        $this->assertDatabaseHas('reading_notes', [
            'id' => $readingNote->id,
            'user_id' => $otherUser->id,
            'note' => 'Protected private note.',
        ]);
    }

    /**
     * A user cannot access reading notes for an unpublished book.
     */
    public function test_user_cannot_access_reading_notes_for_unpublished_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => false,
        ]);

        $this->entitleUser($user, $book);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/notes"
        )->assertNotFound();

        $this->postJson(
            "/api/books/{$book->uuid}/notes",
            [
                'current_page' => 10,
                'location' => 'page-10',
                'note' => 'Test note.',
            ]
        )->assertNotFound();
    }

    /**
     * Create a valid active reading entitlement.
     */
    private function entitleUser(User $user, Book $book): void
    {
        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ]);
    }
}