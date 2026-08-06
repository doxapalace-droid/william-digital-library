<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReadingProgressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest users cannot access reading progress.
     */
    public function test_guest_cannot_view_reading_progress(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson(
            "/api/books/{$book->uuid}/progress"
        );

        $response->assertUnauthorized();
    }

    /**
     * An entitled customer can save reading progress.
     */
    public function test_entitled_user_can_save_reading_progress(): void
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

        $response = $this->putJson(
            "/api/books/{$book->uuid}/progress",
            [
                'current_page' => 25,
                'total_pages' => 100,
                'progress_percentage' => 25,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.current_page', 25)
            ->assertJsonPath('data.total_pages', 100);

        $this->assertDatabaseHas('reading_progress', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 25,
            'total_pages' => 100,
        ]);
    }

    /**
     * Saving progress again updates the existing record
     * instead of creating a duplicate.
     */
    public function test_saving_progress_updates_existing_record(): void
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
            'current_page' => 10,
            'total_pages' => 100,
            'progress_percentage' => 10,
            'last_read_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            "/api/books/{$book->uuid}/progress",
            [
                'current_page' => 40,
                'total_pages' => 100,
                'progress_percentage' => 40,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.current_page', 40)
            ->assertJsonPath('data.progress_percentage', 40);

        $this->assertDatabaseCount('reading_progress', 1);

        $this->assertDatabaseHas('reading_progress', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'current_page' => 40,
        ]);
    }

    /**
     * An entitled customer can retrieve saved progress.
     */
    public function test_entitled_user_can_view_saved_reading_progress(): void
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
            'current_page' => 37,
            'total_pages' => 120,
            'progress_percentage' => 30.83,
            'last_read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/books/{$book->uuid}/progress"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.current_page', 37)
            ->assertJsonPath('data.total_pages', 120);
    }

    /**
     * A customer cannot access progress for a book
     * they are not entitled to read.
     */
    public function test_user_cannot_access_progress_for_unowned_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/books/{$book->uuid}/progress"
        )->assertForbidden();

        $this->putJson(
            "/api/books/{$book->uuid}/progress",
            [
                'current_page' => 5,
                'total_pages' => 100,
                'progress_percentage' => 5,
            ]
        )->assertForbidden();
    }

    /**
     * Reading progress input must be valid.
     */
    public function test_reading_progress_is_validated(): void
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

        $response = $this->putJson(
            "/api/books/{$book->uuid}/progress",
            [
                'current_page' => -1,
                'total_pages' => 0,
                'progress_percentage' => 150,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_page',
                'total_pages',
                'progress_percentage',
            ]);
    }
}