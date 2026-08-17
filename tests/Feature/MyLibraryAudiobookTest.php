<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLibraryAudiobookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An authenticated user can see an audiobook
     * they own through an active entitlement.
     */
    public function test_authenticated_user_can_view_owned_audiobook(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Kingdom Authority',
            'author' => 'William K. Danquah',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement($user, $audiobook);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'audiobooks')
            ->assertJsonPath(
                'audiobooks.0.id',
                $audiobook->id
            )
            ->assertJsonPath(
                'audiobooks.0.uuid',
                $audiobook->uuid
            )
            ->assertJsonPath(
                'audiobooks.0.book_id',
                $book->id
            );
    }

    /**
     * A user cannot see another user's audiobook.
     */
    public function test_user_cannot_see_another_users_audiobook(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $otherUser,
            $audiobook
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * An expired audiobook entitlement does not appear
     * in the user's library.
     */
    public function test_expired_audiobook_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook,
            [
                'expires_at' => now()->subDay(),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * A revoked audiobook entitlement does not appear
     * in the user's library.
     */
    public function test_revoked_audiobook_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook,
            [
                'revoked_at' => now(),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * An inactive audiobook entitlement does not appear
     * in the user's library.
     */
    public function test_inactive_audiobook_entitlement_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook,
            [
                'status' => 'inactive',
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * An entitlement without streaming permission does not
     * give the user access to the audiobook library item.
     */
    public function test_audiobook_without_stream_permission_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook,
            [
                'can_stream' => false,
            ]
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * An inactive audiobook does not appear in the library
     * even when the customer has an active entitlement.
     */
    public function test_inactive_audiobook_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'draft',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * A future audiobook does not appear in the library.
     */
    public function test_future_audiobook_does_not_appear(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->addDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'audiobooks');
    }

    /**
     * The audiobook library response contains the associated
     * book information.
     */
    public function test_audiobook_contains_associated_book_information(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'title' => 'The Power of Binding and Loosing',
            'slug' => 'the-power-of-binding-and-loosing',
            'subtitle' => 'Understanding Kingdom Authority',
            'author' => 'William K. Danquah',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonPath(
                'audiobooks.0.book.id',
                $book->id
            )
            ->assertJsonPath(
                'audiobooks.0.book.uuid',
                $book->uuid
            )
            ->assertJsonPath(
                'audiobooks.0.book.title',
                'The Power of Binding and Loosing'
            )
            ->assertJsonPath(
                'audiobooks.0.book.slug',
                'the-power-of-binding-and-loosing'
            )
            ->assertJsonPath(
                'audiobooks.0.book.subtitle',
                'Understanding Kingdom Authority'
            )
            ->assertJsonPath(
                'audiobooks.0.book.author',
                'William K. Danquah'
            );
    }

    /**
     * Private audiobook file information must not be exposed
     * by the My Library endpoint.
     */
    public function test_library_does_not_expose_private_audiobook_file_paths(): void
    {
        $user = User::factory()->create();

        $book = Book::factory()->create([
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $audiobook = Audiobook::factory()->create([
            'book_id' => $book->id,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->createEntitlement(
            $user,
            $audiobook
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/my-library');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'audio_file' => $audiobook->audio_file,
            ])
            ->assertJsonMissing([
                'audio_path' => $audiobook->audio_path,
            ])
            ->assertJsonMissing([
                'file_path' => $audiobook->file_path,
            ])
            ->assertJsonMissing([
                'storage_path' => $audiobook->storage_path,
            ]);
    }

    /**
     * A guest cannot view the audiobook library.
     */
    public function test_guest_cannot_view_audiobook_library(): void
    {
        $response = $this->getJson('/api/my-library');

        $response->assertUnauthorized();
    }

    /**
     * Create an audiobook entitlement for testing.
     */
    private function createEntitlement(
        User $user,
        Audiobook $audiobook,
        array $overrides = []
    ): AudiobookEntitlement {
        return AudiobookEntitlement::create(
            array_merge(
                [
                    'user_id' => $user->id,
                    'audiobook_id' => $audiobook->id,
                    'source' => 'purchase',
                    'can_stream' => true,
                    'can_download' => true,
                    'status' => 'active',
                    'granted_at' => now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                ],
                $overrides
            )
        );
    }
}