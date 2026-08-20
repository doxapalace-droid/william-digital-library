<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\Course;
use App\Models\CourseEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeProductClaimTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated customer can claim a free book.
     */
    public function test_customer_can_claim_free_book(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_free' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/books/{$book->uuid}/claim"
            );

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Free book claimed successfully.',
            ]);

        $this->assertDatabaseHas('book_entitlements', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'free',
            'can_read' => true,
            'can_download' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Authenticated customer can claim a free audiobook.
     */
    public function test_customer_can_claim_free_audiobook(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'is_free' => true,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/audiobooks/{$audiobook->uuid}/claim"
            );

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Free audiobook claimed successfully.',
            ]);

        $this->assertDatabaseHas('audiobook_entitlements', [
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'free',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Authenticated customer can claim a free course.
     */
    public function test_customer_can_claim_free_course(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'is_free' => true,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/courses/{$course->uuid}/claim"
            );

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Free course claimed successfully.',
            ]);

        $this->assertDatabaseHas('course_entitlements', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'source' => 'free',
            'can_access' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Guest cannot claim a free book.
     */
    public function test_guest_cannot_claim_free_book(): void
    {
        $book = Book::factory()->create([
            'is_free' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/free-products/books/{$book->uuid}/claim"
        );

        $response->assertUnauthorized();
    }

    /**
     * Paid book cannot be claimed as free.
     */
    public function test_paid_book_cannot_be_claimed_as_free(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_free' => false,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/books/{$book->uuid}/claim"
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This book is not available as a free product.',
            ]);

        $this->assertDatabaseMissing('book_entitlements', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * Unpublished free book cannot be claimed.
     */
    public function test_unpublished_free_book_cannot_be_claimed(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_free' => true,
            'is_published' => false,
            'published_at' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/books/{$book->uuid}/claim"
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This book is not available as a free product.',
            ]);
    }

    /**
     * Paid audiobook cannot be claimed as free.
     */
    public function test_paid_audiobook_cannot_be_claimed_as_free(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'is_free' => false,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/audiobooks/{$audiobook->uuid}/claim"
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This audiobook is not available as a free product.',
            ]);

        $this->assertDatabaseMissing(
            'audiobook_entitlements',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ]
        );
    }

    /**
     * Inactive audiobook cannot be claimed.
     */
    public function test_inactive_audiobook_cannot_be_claimed(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'is_free' => true,
            'status' => 'inactive',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/audiobooks/{$audiobook->uuid}/claim"
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This audiobook is not available as a free product.',
            ]);
    }

    /**
     * Paid course cannot be claimed as free.
     */
    public function test_paid_course_cannot_be_claimed_as_free(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'is_free' => false,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/courses/{$course->uuid}/claim"
            );

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' =>
                    'This course is not available as a free product.',
            ]);

        $this->assertDatabaseMissing(
            'course_entitlements',
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]
        );
    }

    /**
     * Claiming a free book twice does not create
     * duplicate ownership.
     */
    public function test_claiming_free_book_twice_does_not_create_duplicate_entitlement(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_free' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/books/{$book->uuid}/claim"
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/books/{$book->uuid}/claim"
            )
            ->assertCreated();

        $this->assertDatabaseCount('book_entitlements', 1);
    }

    /**
     * Claiming a free audiobook twice does not create
     * duplicate ownership.
     */
    public function test_claiming_free_audiobook_twice_does_not_create_duplicate_entitlement(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'is_free' => true,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/audiobooks/{$audiobook->uuid}/claim"
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/audiobooks/{$audiobook->uuid}/claim"
            )
            ->assertCreated();

        $this->assertDatabaseCount(
            'audiobook_entitlements',
            1
        );
    }

    /**
     * Claiming a free course twice does not create
     * duplicate ownership.
     */
    public function test_claiming_free_course_twice_does_not_create_duplicate_entitlement(): void
    {
        $user = User::factory()->create();

        $course = Course::factory()->create([
            'is_free' => true,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/courses/{$course->uuid}/claim"
            )
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(
                "/api/free-products/courses/{$course->uuid}/claim"
            )
            ->assertCreated();

        $this->assertDatabaseCount(
            'course_entitlements',
            1
        );
    }
}