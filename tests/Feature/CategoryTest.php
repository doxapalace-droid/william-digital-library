<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
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
     * Active categories can be listed publicly.
     */
    public function test_active_categories_can_be_listed(): void
    {
        Category::create([
            'name' => 'Christian Living',
            'slug' => 'christian-living',
            'description' => 'Books about Christian living.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'description' => 'This category should not appear.',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Christian Living');
    }

    /**
     * A single category can be viewed publicly.
     */
    public function test_single_category_can_be_viewed(): void
    {
        $category = Category::create([
            'name' => 'Leadership',
            'slug' => 'leadership',
            'description' => 'Books about leadership.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/categories/{$category->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Leadership')
            ->assertJsonPath('data.slug', 'leadership');
    }

    /**
     * Admin can create a category.
     */
    public function test_admin_can_create_a_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this
            ->actingAs($admin)
            ->postJson('/api/admin/categories', [
                'name' => 'Prayer',
                'slug' => 'prayer',
                'description' => 'Books about prayer.',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Category created successfully.'
            )
            ->assertJsonPath('data.name', 'Prayer')
            ->assertJsonPath('data.slug', 'prayer');

        $this->assertDatabaseHas('categories', [
            'name' => 'Prayer',
            'slug' => 'prayer',
            'is_active' => true,
        ]);
    }

    /**
     * Admin can update a category.
     */
    public function test_admin_can_update_a_category(): void
    {
        $admin = $this->createAdmin();

        $category = Category::create([
            'name' => 'Prayer',
            'slug' => 'prayer',
            'description' => 'Books about prayer.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(
                "/api/admin/categories/{$category->id}",
                [
                    'name' => 'Prayer and Intercession',
                    'slug' => 'prayer-and-intercession',
                    'description' => 'Books about prayer and intercession.',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Category updated successfully.'
            )
            ->assertJsonPath(
                'data.name',
                'Prayer and Intercession'
            );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Prayer and Intercession',
            'slug' => 'prayer-and-intercession',
        ]);
    }

    /**
     * Admin can delete a category.
     */
    public function test_admin_can_delete_a_category(): void
    {
        $admin = $this->createAdmin();

        $category = Category::create([
            'name' => 'Temporary Category',
            'slug' => 'temporary-category',
            'description' => 'Temporary category.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(
                "/api/admin/categories/{$category->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Category deleted successfully.'
            );

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    /**
     * Customer cannot create a category.
     */
    public function test_customer_cannot_create_a_category(): void
    {
        $customer = $this->createCustomer();

        $response = $this
            ->actingAs($customer)
            ->postJson('/api/admin/categories', [
                'name' => 'Unauthorized Category',
                'slug' => 'unauthorized-category',
                'description' => 'Should not be created.',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('categories', [
            'slug' => 'unauthorized-category',
        ]);
    }

    /**
     * Guest cannot create a category.
     */
    public function test_guest_cannot_create_a_category(): void
    {
        $response = $this->postJson(
            '/api/admin/categories',
            [
                'name' => 'Guest Category',
                'slug' => 'guest-category',
                'description' => 'Should not be created.',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('categories', [
            'slug' => 'guest-category',
        ]);
    }
}