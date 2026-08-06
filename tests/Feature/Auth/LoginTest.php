<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertNoContent();

        $this->assertAuthenticatedAs($user);
    }


    public function test_authenticated_customer_can_retrieve_their_profile(): void
{
    $user = User::factory()->create([
        'name' => 'William Test Customer',
        'email' => 'profile@example.com',
        'password' => Hash::make('Password123!'),
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/user');

    $response
        ->assertOk()
        ->assertJsonPath('email', 'profile@example.com')
        ->assertJsonPath('name', 'William Test Customer');

    $this->assertAuthenticatedAs($user);
}

}