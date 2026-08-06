<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Models\BookEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_without_entitlement_cannot_open_book_reader(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Protected Book',
            'slug' => 'protected-book',
            'description' => 'A protected digital book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/books/{$book->id}/read");

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not have access to this book.',
            ]);
    }

    public function test_customer_with_entitlement_can_open_book_reader(): void
{
    $user = User::factory()->create();

    $book = Book::create([
        'title' => 'Born to Rule',
        'slug' => 'born-to-rule',
        'description' => 'Kingdom authority',
        'author' => 'William K. Danquah',
        'price' => 6.99,
        'currency' => 'USD',
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
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson("/api/books/{$book->id}/read");

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Book access granted.',
        ])
        ->assertJsonStructure([
            'message',
            'book' => [
                'uuid',
                'title',
                'author',
            ],
        ]);
    }

    public function test_customer_with_expired_entitlement_cannot_open_book_reader(): void
{
    $user = User::factory()->create();

    $book = Book::create([
        'title' => 'Expired Access Book',
        'slug' => 'expired-access-book',
        'description' => 'A protected digital book.',
        'author' => 'William K. Danquah',
        'price' => 6.99,
        'currency' => 'USD',
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
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson("/api/books/{$book->id}/read");

    $response
        ->assertStatus(403)
        ->assertJson([
            'message' => 'You do not have access to this book.',
        ]);
    }

}