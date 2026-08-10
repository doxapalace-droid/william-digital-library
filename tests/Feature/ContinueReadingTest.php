<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContinueReadingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot access Continue Reading.
     */
    public function test_guest_cannot_view_continue_reading(): void
    {
        $response = $this->getJson('/api/continue-reading');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated user can retrieve Continue Reading.
     */
    public function test_user_can_view_continue_reading_books(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'revoked_at' => null,
            'expires_at' => null,
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'total_pages' => 100,
            'progress_percentage' => 25,
            'last_read_at' => now(),
        ]);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'uuid',
                        'title',
                        'slug',
                        'current_page',
                        'total_pages',
                        'progress_percentage',
                        'last_read_at',
                    ]
                ]
            ])
            ->assertJsonPath('data.0.id', $book->id);
    }

    /**
     * Books without progress should not appear.
     */
    public function test_book_without_progress_does_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
        ]);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Users only see their own progress.
     */
    public function test_user_cannot_see_another_users_continue_reading(): void
    {
        $user = User::factory()->create();

        $other = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $other->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $other->id,
            'book_id' => $book->id,
            'progress_percentage' => 50,
            'last_read_at' => now(),
        ]);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Expired entitlement should not appear.
     */
    public function test_expired_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'expires_at' => now()->subDay(),
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'progress_percentage' => 25,
            'last_read_at' => now(),
        ]);

        $this->getJson('/api/continue-reading')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Revoked entitlement should not appear.
     */
    public function test_revoked_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
            'revoked_at' => now(),
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'progress_percentage' => 30,
            'last_read_at' => now(),
        ]);

        $this->getJson('/api/continue-reading')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Inactive entitlement should not appear.
     */
    public function test_inactive_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'inactive',
            'can_read' => true,
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'progress_percentage' => 20,
            'last_read_at' => now(),
        ]);

        $this->getJson('/api/continue-reading')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Completed books should not appear.
     */
    public function test_completed_books_do_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'progress_percentage' => 100,
            'current_page' => 100,
            'total_pages' => 100,
        ]);

        $this->getJson('/api/continue-reading')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Unpublished books should not appear.
     */
    public function test_unpublished_books_do_not_appear(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $book = Book::factory()->create([
            'is_published' => false,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => 'active',
            'can_read' => true,
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'progress_percentage' => 15,
            'last_read_at' => now(),
        ]);

        $this->getJson('/api/continue-reading')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Continue Reading should be ordered by most recent activity.
     */
    public function test_continue_reading_is_ordered_by_last_read_at(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $olderBook = Book::factory()->create(['is_published' => true]);

        $newerBook = Book::factory()->create(['is_published' => true]);

        foreach ([$olderBook, $newerBook] as $book) {

            BookEntitlement::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'status' => 'active',
                'can_read' => true,
            ]);
        }

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $olderBook->id,
            'progress_percentage' => 20,
            'last_read_at' => now()->subHours(3),
        ]);

        ReadingProgress::factory()->create([
            'user_id' => $user->id,
            'book_id' => $newerBook->id,
            'progress_percentage' => 40,
            'last_read_at' => now(),
        ]);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerBook->id)
            ->assertJsonPath('data.1.id', $olderBook->id);
    }
}