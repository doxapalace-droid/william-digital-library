<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an admin user for testing.
     */
    private function createAdmin(): User
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
            'status' => true,
        ]);

        return User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
    }

    /**
     * Create a customer user for testing.
     */
    private function createCustomer(): User
    {
        $customerRole = Role::create([
            'name' => 'Customer',
            'slug' => 'customer',
            'description' => 'Library customer',
            'is_system' => false,
            'status' => true,
        ]);

        return User::factory()->create([
            'role_id' => $customerRole->id,
        ]);
    }

    /**
     * Active authors can be listed publicly.
     */
    public function test_active_authors_can_be_listed(): void
    {
        Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and teacher.',
            'is_active' => true,
        ]);

        Author::create([
            'name' => 'Hidden Author',
            'slug' => 'hidden-author',
            'bio' => 'This author should not appear.',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/authors');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'William K. Danquah'
            );
    }

    /**
     * A single author can be viewed publicly.
     */
    public function test_single_author_can_be_viewed(): void
    {
        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and teacher.',
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/authors/{$author->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $author->id
            )
            ->assertJsonPath(
                'data.name',
                'William K. Danquah'
            )
            ->assertJsonPath(
                'data.slug',
                'william-k-danquah'
            );
    }

    /**
     * Admin can create an author.
     */
    public function test_admin_can_create_an_author(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/authors', [
                'name' => 'John Doe',
                'slug' => 'john-doe',
                'bio' => 'A Christian author.',
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Author created successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'John Doe'
            )
            ->assertJsonPath(
                'data.slug',
                'john-doe'
            );

        $this->assertDatabaseHas('authors', [
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'is_active' => true,
        ]);
    }

    /**
     * Admin can update an author.
     */
    public function test_admin_can_update_an_author(): void
    {
        $admin = $this->createAdmin();

        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Original biography.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/authors/{$author->id}",
                [
                    'name' => 'John Doe Updated',
                    'slug' => 'john-doe-updated',
                    'bio' => 'Updated biography.',
                    'is_active' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Author updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'John Doe Updated'
            )
            ->assertJsonPath(
                'data.slug',
                'john-doe-updated'
            );

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'John Doe Updated',
            'slug' => 'john-doe-updated',
        ]);
    }

    /**
     * Admin can delete an author.
     */
    public function test_admin_can_delete_an_author(): void
    {
        $admin = $this->createAdmin();

        $author = Author::create([
            'name' => 'Temporary Author',
            'slug' => 'temporary-author',
            'bio' => 'Temporary author.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(
                "/api/admin/authors/{$author->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Author deleted successfully.'
            );

        $this->assertDatabaseMissing('authors', [
            'id' => $author->id,
        ]);
    }

    /**
     * Customer cannot create an author.
     */
    public function test_customer_cannot_create_an_author(): void
    {
        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/admin/authors', [
                'name' => 'Unauthorized Author',
                'slug' => 'unauthorized-author',
                'bio' => 'Should not be created.',
                'is_active' => true,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('authors', [
            'slug' => 'unauthorized-author',
        ]);
    }

    /**
     * Guest cannot create an author.
     */
    public function test_guest_cannot_create_an_author(): void
    {
        $response = $this->postJson(
            '/api/admin/authors',
            [
                'name' => 'Guest Author',
                'slug' => 'guest-author',
                'bio' => 'Should not be created.',
                'is_active' => true,
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('authors', [
            'slug' => 'guest-author',
        ]);
    }

    /**
     * Author name must be unique.
     */
    public function test_author_name_must_be_unique(): void
    {
        $admin = $this->createAdmin();

        Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Existing author.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/authors', [
                'name' => 'William K. Danquah',
                'slug' => 'another-slug',
                'bio' => 'Duplicate author.',
                'is_active' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    /**
     * Author slug must be unique.
     */
    public function test_author_slug_must_be_unique(): void
    {
        $admin = $this->createAdmin();

        Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Existing author.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/authors', [
                'name' => 'Another Author',
                'slug' => 'william-k-danquah',
                'bio' => 'Duplicate slug.',
                'is_active' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }
}