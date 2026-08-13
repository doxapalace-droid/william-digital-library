<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookEntitlementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customer can be granted access to a book.
     */
    public function test_customer_can_be_granted_access_to_a_book(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Born to Rule',
            'slug' => 'born-to-rule',
            'description' => 'A book about spiritual authority and dominion.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $entitlement = BookEntitlement::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'can_read' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
        ]);

        $this->assertDatabaseHas('book_entitlements', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'source' => 'purchase',
            'status' => 'active',
        ]);

        $this->assertTrue($entitlement->can_read);
        $this->assertFalse($entitlement->can_download);

        $this->assertTrue(
            $entitlement->user->is($user)
        );

        $this->assertTrue(
            $entitlement->book->is($book)
        );
    }

    /**
     * Customer with active entitlement can read a book.
     */
    public function test_customer_with_active_entitlement_can_read_a_book(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'description' => 'A book about exercising spiritual authority.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
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
        ]);

        $this->assertTrue(
            $user->canReadBook($book)
        );
    }

    /**
     * Customer without entitlement cannot read a book.
     */
    public function test_customer_without_entitlement_cannot_read_a_book(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Restricted Book',
            'slug' => 'restricted-book',
            'description' => 'A protected digital book.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertFalse(
            $user->canReadBook($book)
        );
    }

    /**
     * Customer with expired entitlement cannot read a book.
     */
    public function test_customer_with_expired_entitlement_cannot_read_a_book(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Expired Access Book',
            'slug' => 'expired-access-book',
            'description' => 'A protected digital book with expired access.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
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

        $this->assertFalse(
            $user->canReadBook($book)
        );
    }

    /**
     * Customer with inactive entitlement cannot read a book.
     */
    public function test_customer_with_inactive_entitlement_cannot_read_a_book(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'title' => 'Inactive Access Book',
            'slug' => 'inactive-access-book',
            'description' => 'A protected digital book with inactive access.',
            'author' => 'William K. Danquah',
            'price' => 6.99,
            'currency' => 'USD',
            'is_published' => true,
            'published_at' => now(),
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
        ]);

        $this->assertFalse(
            $user->canReadBook($book)
        );
    }
}