<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_my_library(): void
    {
        $response = $this->getJson('/api/my-library');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_books_in_their_library(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'My Purchased Book',
            'slug' => 'my-purchased-book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $book->id)
            ->assertJsonPath('data.0.title', 'My Purchased Book');
    }

    public function test_user_cannot_see_another_users_books(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Another Users Book',
            'slug' => 'another-users-book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_expired_entitlement_does_not_appear_in_library(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Expired Book',
            'slug' => 'expired-book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_valid_future_entitlement_appears_in_library(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Subscription Book',
            'slug' => 'subscription-book',
            'is_published' => true,
            'published_at' => now(),
        ]);

        BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'expires_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $book->id);
    }
}