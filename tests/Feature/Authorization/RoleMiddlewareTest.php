<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'role:admin'])
            ->get('/test/admin', fn () => response()->json([
                'message' => 'Admin access granted',
            ]));

        Route::middleware(['web', 'role:customer'])
            ->get('/test/customer', fn () => response()->json([
                'message' => 'Customer access granted',
            ]));
    }

    public function test_admin_can_access_admin_route(): void
    {
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator',
            'is_system' => true,
            'status' => true,
        ]);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/test/admin');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Admin access granted',
            ]);
    }

    public function test_customer_can_access_customer_route(): void
    {
        $customerRole = Role::create([
            'name' => 'Customer',
            'slug' => 'customer',
            'description' => 'Library customer',
            'is_system' => false,
            'status' => true,
        ]);

        $customer = User::factory()->create([
            'role_id' => $customerRole->id,
        ]);

        $response = $this->actingAs($customer)->getJson('/test/customer');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Customer access granted',
            ]);
    }

    public function test_customer_cannot_access_admin_route(): void
    {
        $customerRole = Role::create([
            'name' => 'Customer',
            'slug' => 'customer',
            'description' => 'Library customer',
            'is_system' => false,
            'status' => true,
        ]);

        $customer = User::factory()->create([
            'role_id' => $customerRole->id,
        ]);

        $response = $this->actingAs($customer)->getJson('/test/admin');

        $response->assertForbidden();
    }
}