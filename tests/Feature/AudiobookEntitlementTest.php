<?php

namespace Tests\Feature;

use App\Models\Audiobook;
use App\Models\AudiobookEntitlement;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class AudiobookEntitlementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An audiobook entitlement belongs to a user.
     */
    public function test_entitlement_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        $entitlement = AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $this->assertTrue(
            $entitlement->user->is($user)
        );
    }

    /**
     * An audiobook entitlement belongs to an audiobook.
     */
    public function test_entitlement_belongs_to_audiobook(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now(),
        ]);

        $entitlement = AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $this->assertTrue(
            $entitlement->audiobook->is($audiobook)
        );
    }

    /**
     * Audiobook entitlement has a UUID.
     */
    public function test_entitlement_has_uuid(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
        ]);

        $entitlement = AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $this->assertNotNull($entitlement->uuid);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $entitlement->uuid
        );
    }

    /**
     * Audiobook entitlement values are cast correctly.
     */
    public function test_entitlement_values_are_cast_correctly(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
        ]);

        $entitlement = AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->assertTrue($entitlement->can_stream);
        $this->assertFalse($entitlement->can_download);

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $entitlement->granted_at
        );

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $entitlement->expires_at
        );
    }

    /**
     * An active entitlement is active.
     */
    public function test_active_entitlement_is_active(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'granted_at' => now(),
        ]);

        $this->assertTrue(
            $entitlement->isActive()
        );
    }

    /**
     * A revoked entitlement is not active.
     */
    public function test_revoked_entitlement_is_not_active(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'granted_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * An expired entitlement is not active.
     */
    public function test_expired_entitlement_is_not_active(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'granted_at' => now()->subYear(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * An inactive entitlement is not active.
     */
    public function test_inactive_entitlement_is_not_active(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'inactive',
            'granted_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->isActive()
        );
    }

    /**
     * An active entitlement with streaming permission
     * allows streaming.
     */
    public function test_active_entitlement_can_stream(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_stream' => true,
            'granted_at' => now(),
        ]);

        $this->assertTrue(
            $entitlement->canStream()
        );
    }

    /**
     * An active entitlement without streaming permission
     * cannot stream.
     */
    public function test_entitlement_without_stream_permission_cannot_stream(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_stream' => false,
            'granted_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->canStream()
        );
    }

    /**
     * A revoked entitlement cannot stream.
     */
    public function test_revoked_entitlement_cannot_stream(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_stream' => true,
            'granted_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->canStream()
        );
    }

    /**
     * An expired entitlement cannot stream.
     */
    public function test_expired_entitlement_cannot_stream(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_stream' => true,
            'granted_at' => now()->subYear(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $entitlement->canStream()
        );
    }

    /**
     * An active entitlement with download permission
     * allows downloading.
     */
    public function test_active_entitlement_can_download(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_download' => true,
            'granted_at' => now(),
        ]);

        $this->assertTrue(
            $entitlement->canDownload()
        );
    }

    /**
     * An active entitlement without download permission
     * cannot download.
     */
    public function test_entitlement_without_download_permission_cannot_download(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_download' => false,
            'granted_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->canDownload()
        );
    }

    /**
     * A revoked entitlement cannot download.
     */
    public function test_revoked_entitlement_cannot_download(): void
    {
        $entitlement = $this->createEntitlement([
            'status' => 'active',
            'can_download' => true,
            'granted_at' => now(),
            'revoked_at' => now(),
        ]);

        $this->assertFalse(
            $entitlement->canDownload()
        );
    }

    /**
     * A customer cannot have duplicate entitlement
     * records for the same audiobook.
     */
    public function test_duplicate_audiobook_entitlement_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $user = User::factory()->create();
        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
        ]);

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);

        AudiobookEntitlement::create([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
        ]);
    }

    /**
     * Create an audiobook entitlement for testing.
     */
    private function createEntitlement(array $overrides = []): AudiobookEntitlement
    {
        $user = User::factory()->create();

        $book = Book::factory()->create();

        $audiobook = Audiobook::create([
            'book_id' => $book->id,
            'price' => 25.00,
            'currency' => 'USD',
            'status' => 'active',
            'duration_seconds' => 3600,
            'published_at' => now()->subMinute(),
        ]);

        return AudiobookEntitlement::create(array_merge([
            'user_id' => $user->id,
            'audiobook_id' => $audiobook->id,
            'source' => 'purchase',
            'can_stream' => true,
            'can_download' => false,
            'status' => 'active',
            'granted_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
        ], $overrides));
    }
}