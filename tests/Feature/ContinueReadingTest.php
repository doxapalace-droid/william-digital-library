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
     * Guest users cannot access continue reading.
     */
    public function test_guest_cannot_view_continue_reading(): void
    {
        $response = $this->getJson('/api/continue-reading');

        $response->assertUnauthorized();
    }

    /**
     * An authenticated customer can see books
     * they have started reading.
     */
    public function test_user_can_view_continue_reading_books(): void
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

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'total_pages' => 100,
            'progress_percentage' => 25,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.current_page', 25)
            ->assertJsonPath('data.0.total_pages', 100)
            ->assertJsonPath('data.0.progress_percentage', 25);
    }

    /**
     * Books without reading progress should not appear.
     */
    public function test_book_without_progress_does_not_appear(): void
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

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A user cannot see another customer's reading progress.
     */
    public function test_user_cannot_see_another_users_continue_reading(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        ReadingProgress::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'current_page' => 50,
            'total_pages' => 100,
            'progress_percentage' => 50,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Progress for an expired entitlement should not appear.
     */
    public function test_expired_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => now()->subDay(),
        ]);

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 20,
            'total_pages' => 100,
            'progress_percentage' => 20,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A valid future entitlement should appear.
     */
    public function test_valid_future_entitlement_appears(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'subscription',
            'expires_at' => now()->addDays(30),
        ]);

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 30,
            'total_pages' => 100,
            'progress_percentage' => 30,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $book->id);
    }

    /**
     * Continue reading is ordered by most recently read.
     */
    public function test_continue_reading_is_ordered_by_last_read_at(): void
    {
        $user = User::factory()->create();

        $olderBook = Book::factory()->create([
            'is_published' => true,
        ]);

        $newerBook = Book::factory()->create([
            'is_published' => true,
        ]);

        foreach ([$olderBook, $newerBook] as $book) {
            BookEntitlement::create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'source' => 'purchase',
                'expires_at' => null,
            ]);
        }

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $olderBook->id,
            'current_page' => 10,
            'total_pages' => 100,
            'progress_percentage' => 10,
            'last_read_at' => now()->subHours(2),
        ]);

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $newerBook->id,
            'current_page' => 40,
            'total_pages' => 100,
            'progress_percentage' => 40,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerBook->id)
            ->assertJsonPath('data.1.id', $olderBook->id);
    }

    /**
     * Unpublished books should not appear in continue reading.
     */
    public function test_unpublished_book_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => false,
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'expires_at' => null,
        ]);

        ReadingProgress::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 15,
            'total_pages' => 100,
            'progress_percentage' => 15,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/continue-reading');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}