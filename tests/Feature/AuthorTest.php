<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
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
     * Inactive authors are excluded from the public list.
     */
    public function test_inactive_authors_are_not_listed(): void
    {
        Author::create([
            'name' => 'Active Author',
            'slug' => 'active-author',
            'bio' => 'Active author.',
            'is_active' => true,
        ]);

        Author::create([
            'name' => 'Inactive Author',
            'slug' => 'inactive-author',
            'bio' => 'Inactive author.',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/authors');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Active Author'
            )
            ->assertJsonMissing([
                'name' => 'Inactive Author',
            ]);
    }

    /**
     * A single active author can be viewed publicly.
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
     * An inactive author cannot be viewed publicly.
     */
    public function test_inactive_author_cannot_be_viewed(): void
    {
        $author = Author::create([
            'name' => 'Inactive Author',
            'slug' => 'inactive-author',
            'bio' => 'This author is inactive.',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            "/api/authors/{$author->id}"
        );

        $response->assertNotFound();
    }

    /**
     * An author displays only published books.
     */
    public function test_author_displays_only_published_books(): void
    {
        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Author and teacher.',
            'is_active' => true,
        ]);

        $publishedBook = Book::factory()->create([
            'title' => 'Published Book',
            'is_published' => true,
        ]);

        $unpublishedBook = Book::factory()->create([
            'title' => 'Unpublished Book',
            'is_published' => false,
        ]);

        $author->books()->attach([
            $publishedBook->id,
            $unpublishedBook->id,
        ]);

        $response = $this->getJson(
            "/api/authors/{$author->id}"
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Published Book',
            ])
            ->assertJsonMissing([
                'title' => 'Unpublished Book',
            ]);
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
            'bio' => 'Updated biography.',
        ]);
    }

    /**
     * Customer cannot update an author.
     */
    public function test_customer_cannot_update_an_author(): void
    {
        $customer = $this->createCustomer();

        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Original biography.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($customer)
            ->putJson(
                "/api/admin/authors/{$author->id}",
                [
                    'name' => 'Unauthorized Update',
                    'slug' => 'unauthorized-update',
                    'bio' => 'Should not be updated.',
                    'is_active' => true,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'John Doe',
            'slug' => 'john-doe',
        ]);
    }

    /**
     * Guest cannot update an author.
     */
    public function test_guest_cannot_update_an_author(): void
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Original biography.',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/admin/authors/{$author->id}",
            [
                'name' => 'Updated Author',
                'slug' => 'updated-author',
                'bio' => 'Updated biography.',
                'is_active' => true,
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'John Doe',
            'slug' => 'john-doe',
        ]);
    }

    /**
     * An author can keep its existing name and slug during an update.
     */
    public function test_author_can_keep_existing_name_and_slug_when_updated(): void
    {
        $admin = $this->createAdmin();

        $author = Author::create([
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Original biography.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/authors/{$author->id}",
                [
                    'name' => 'William K. Danquah',
                    'slug' => 'william-k-danquah',
                    'bio' => 'Updated biography.',
                    'is_active' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'William K. Danquah'
            )
            ->assertJsonPath(
                'data.slug',
                'william-k-danquah'
            );

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'name' => 'William K. Danquah',
            'slug' => 'william-k-danquah',
            'bio' => 'Updated biography.',
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
     * Customer cannot delete an author.
     */
    public function test_customer_cannot_delete_an_author(): void
    {
        $customer = $this->createCustomer();

        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Author biography.',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($customer)
            ->deleteJson(
                "/api/admin/authors/{$author->id}"
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
        ]);
    }

    /**
     * Guest cannot delete an author.
     */
    public function test_guest_cannot_delete_an_author(): void
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'bio' => 'Author biography.',
            'is_active' => true,
        ]);

        $response = $this->deleteJson(
            "/api/admin/authors/{$author->id}"
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
        ]);
    }

    /**
     * Deleting an author detaches the author from books
     * without deleting the books themselves.
     */
    public function test_deleting_author_detaches_author_from_books(): void
    {
        $admin = $this->createAdmin();

        $author = Author::create([
            'name' => 'Temporary Author',
            'slug' => 'temporary-author',
            'bio' => 'Temporary author.',
            'is_active' => true,
        ]);

        $book = Book::factory()->create([
            'is_published' => true,
        ]);

        $author->books()->attach($book->id);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(
                "/api/admin/authors/{$author->id}"
            );

        $response->assertOk();

        $this->assertDatabaseMissing('authors', [
            'id' => $author->id,
        ]);

        $this->assertDatabaseMissing('author_book', [
            'author_id' => $author->id,
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
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