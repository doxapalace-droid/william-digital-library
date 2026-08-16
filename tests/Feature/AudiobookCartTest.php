<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookCartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest cannot view the cart.
     */
    public function test_guest_cannot_view_cart(): void
    {
        $response = $this->getJson(
            '/api/cart'
        );

        $response->assertUnauthorized();
    }

    /**
     * Guest cannot add an audiobook to the cart.
     */
    public function test_guest_cannot_add_audiobook_to_cart(): void
    {
        $audiobook = $this->createAudiobook();

        $response = $this->postJson(
            '/api/cart',
            [
                'audiobook_uuid' => $audiobook->uuid,
            ]
        );

        $response->assertUnauthorized();
    }

    /**
     * Authenticated user can add an audiobook to the cart.
     */
    public function test_user_can_add_audiobook_to_cart(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'price' => 35.00,
            'currency' => 'USD',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        $response->assertJsonPath(
            'data.type',
            'audiobook'
        );

        $response->assertJsonPath(
            'data.audiobook.uuid',
            $audiobook->uuid
        );

        $response->assertJsonPath(
            'data.unit_price',
            '35.00'
        );

        $response->assertJsonPath(
            'data.subtotal',
            '35.00'
        );

        $this->assertDatabaseHas(
            'cart_items',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
                'book_id' => null,
                'unit_price' => 35.00,
                'quantity' => 1,
                'subtotal' => 35.00,
            ]
        );
    }

    /**
     * Audiobook cart item contains its parent book information.
     */
    public function test_audiobook_cart_item_contains_book_information(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'Kingdom Audio',
            'slug' => 'kingdom-audio',
            'subtitle' => 'Walking in Dominion',
            'cover_image' => 'books/kingdom.jpg',
        ]);

        $audiobook = $this->createAudiobook([
            'book_id' => $book->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        $response->assertJsonPath(
            'data.audiobook.title',
            'Kingdom Audio'
        );

        $response->assertJsonPath(
            'data.audiobook.slug',
            'kingdom-audio'
        );

        $response->assertJsonPath(
            'data.audiobook.subtitle',
            'Walking in Dominion'
        );

        $response->assertJsonPath(
            'data.audiobook.cover_image',
            'books/kingdom.jpg'
        );
    }

    /**
     * Audiobook price is captured when added to the cart.
     */
    public function test_audiobook_cart_item_keeps_captured_price(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'price' => 40.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        /*
         * Change the audiobook price after it has been
         * added to the cart.
         */
        $audiobook->update([
            'price' => 75.00,
        ]);

        $cartResponse = $this
            ->actingAs($user)
            ->getJson('/api/cart');

        $cartResponse->assertOk();

        $cartResponse->assertJsonPath(
            'data.0.unit_price',
            '40.00'
        );

        $cartResponse->assertJsonPath(
            'data.0.subtotal',
            '40.00'
        );
    }

    /**
     * User cannot add the same audiobook twice.
     */
    public function test_user_cannot_add_same_audiobook_twice(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $firstResponse = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $firstResponse->assertCreated();

        $secondResponse = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $secondResponse->assertUnprocessable();

        $secondResponse->assertJsonValidationErrors([
            'audiobook_uuid',
        ]);

        $this->assertDatabaseCount(
            'cart_items',
            1
        );
    }

    /**
     * Unavailable audiobook cannot be added.
     */
    public function test_inactive_audiobook_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'audiobook_uuid',
        ]);
    }

    /**
     * Draft audiobook cannot be added.
     */
    public function test_draft_audiobook_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'audiobook_uuid',
        ]);
    }

    /**
     * Future audiobook cannot be added.
     */
    public function test_future_audiobook_cannot_be_added(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'published_at' => now()->addDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'audiobook_uuid',
        ]);
    }

    /**
     * User cannot add an audiobook they already own.
     */
    public function test_user_cannot_add_audiobook_they_already_own(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'audiobook_uuid',
        ]);

        $this->assertDatabaseMissing(
            'cart_items',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ]
        );
    }

    /**
     * Expired audiobook entitlement does not block purchase.
     */
    public function test_expired_entitlement_does_not_block_audiobook_cart(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ]
        );
    }

    /**
     * Revoked audiobook entitlement does not block purchase.
     */
    public function test_revoked_entitlement_does_not_block_audiobook_cart(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => true,
            'status' => 'active',
            'granted_at' => now()->subDay(),
            'revoked_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'user_id' => $user->id,
                'audiobook_id' => $audiobook->id,
            ]
        );
    }

    /**
     * User cannot submit both book and audiobook.
     */
    public function test_user_cannot_add_book_and_audiobook_together(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $audiobook = $this->createAudiobook();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'book_uuid' => $book->uuid,
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'book_uuid',
            'audiobook_uuid',
        ]);
    }

    /**
     * User cannot add an empty cart request.
     */
    public function test_cart_item_type_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                []
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'book_uuid',
            'audiobook_uuid',
        ]);
    }

    /**
     * User can view audiobook in cart.
     */
    public function test_user_can_view_audiobook_in_cart(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook([
            'price' => 50.00,
            'currency' => 'USD',
        ]);

        CartItem::create([
            'user_id' => $user->id,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'unit_price' => 50.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 50.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/cart');

        $response->assertOk();

        $response->assertJsonCount(
            1,
            'data'
        );

        $response->assertJsonPath(
            'data.0.type',
            'audiobook'
        );

        $response->assertJsonPath(
            'data.0.audiobook.uuid',
            $audiobook->uuid
        );

        $response->assertJsonPath(
            'subtotal',
            50
        );

        $response->assertJsonPath(
            'total',
            50
        );
    }

    /**
     * Cart keeps book and audiobook items separate.
     */
    public function test_cart_can_contain_book_and_audiobook(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'price' => 20.00,
            'currency' => 'USD',
        ]);

        $audiobook = $this->createAudiobook([
            'price' => 35.00,
            'currency' => 'USD',
        ]);

        $bookResponse = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'book_uuid' => $book->uuid,
                ]
            );

        $bookResponse->assertCreated();

        $audiobookResponse = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $audiobookResponse->assertCreated();

        $cartResponse = $this
            ->actingAs($user)
            ->getJson('/api/cart');

        $cartResponse->assertOk();

        $cartResponse->assertJsonCount(
            2,
            'data'
        );

        $cartResponse->assertJsonPath(
            'subtotal',
            55
        );

        $cartResponse->assertJsonPath(
            'total',
            55
        );

        $this->assertDatabaseCount(
            'cart_items',
            2
        );
    }

    /**
     * User only sees their own audiobook cart items.
     */
    public function test_user_only_sees_their_own_audiobook_cart_items(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $audiobook = $this->createAudiobook();

        CartItem::create([
            'user_id' => $otherUser->id,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/cart');

        $response->assertOk();

        $response->assertJsonCount(
            0,
            'data'
        );

        $response->assertJsonPath(
            'subtotal',
            0
        );

        $response->assertJsonPath(
            'total',
            0
        );
    }

    /**
     * Removing an audiobook cart item works.
     */
    public function test_user_can_remove_audiobook_from_cart(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $cartItem = CartItem::create([
            'user_id' => $user->id,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/cart/{$cartItem->uuid}"
            );

        $response->assertOk();

        $response->assertJson([
            'message' => 'Cart item removed successfully.',
        ]);

        $this->assertDatabaseMissing(
            'cart_items',
            [
                'id' => $cartItem->id,
            ]
        );
    }

    /**
     * User cannot remove another user's audiobook cart item.
     */
    public function test_user_cannot_remove_another_users_audiobook_cart_item(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $cartItem = CartItem::create([
            'user_id' => $otherUser->id,
            'book_id' => null,
            'audiobook_id' => $audiobook->id,
            'unit_price' => 30.00,
            'currency' => 'USD',
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/cart/{$cartItem->uuid}"
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'cart_items',
            [
                'id' => $cartItem->id,
            ]
        );
    }

    /**
     * Cart response does not expose private audio file paths.
     */
    public function test_cart_does_not_expose_audio_file(): void
    {
        $user = User::factory()->create();

        $audiobook = $this->createAudiobook();

        $audiobook->chapters()->create([
            'title' => 'Chapter One',
            'description' => 'First chapter.',
            'track_number' => 1,
            'audio_file' => 'private/audio/chapter-one.mp3',
            'duration_seconds' => 300,
            'status' => 'active',
            'is_preview' => false,
            'published_at' => now()->subMinute(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/cart',
                [
                    'audiobook_uuid' => $audiobook->uuid,
                ]
            );

        $response->assertCreated();

        $response->assertJsonMissing([
            'audio_file' => 'private/audio/chapter-one.mp3',
        ]);
    }

    /**
     * Create a standard active audiobook.
     */
    private function createAudiobook(
        array $overrides = []
    ): Audiobook {
        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        return Audiobook::create(
            array_merge(
                [
                    'book_id' => $book->id,
                    'description' => 'Test audiobook.',
                    'cover_image' => null,
                    'price' => 25.00,
                    'currency' => 'USD',
                    'status' => 'active',
                    'duration_seconds' => 3600,
                    'published_at' => now()->subMinute(),
                ],
                $overrides
            )
        );
    }
}