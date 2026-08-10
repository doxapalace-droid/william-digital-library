<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot access recommendations.
     */
    public function test_guest_cannot_view_recommendations(): void
    {
        $this->getJson('/api/recommendations')
            ->assertUnauthorized();
    }

    /**
     * User can retrieve recommendations.
     */
    public function test_user_can_view_recommendations(): void
    {
        $user = User::factory()->create();

        Book::factory()->count(5)->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    /**
     * Books already owned should not be recommended.
     */
    public function test_owned_books_are_not_recommended(): void
    {
        $user = User::factory()->create();

        $ownedBook = Book::factory()->create([
            'is_published' => true,
        ]);

        $recommendedBook = Book::factory()->create([
            'is_published' => true,
        ]);

        BookEntitlement::factory()->create([
            'user_id' => $user->id,
            'book_id' => $ownedBook->id,
            'expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response
            ->assertOk()
            ->assertJsonMissing([
                'id' => $ownedBook->id,
            ])
            ->assertJsonFragment([
                'id' => $recommendedBook->id,
            ]);
    }

    /**
     * Unpublished books should never appear.
     */
    public function test_unpublished_books_are_not_recommended(): void
    {
        $user = User::factory()->create();

        Book::factory()->create([
            'is_published' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Recommendations are limited to ten books.
     */
    public function test_recommendations_are_limited_to_ten_books(): void
    {
        $user = User::factory()->create();

        Book::factory()->count(20)->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }

    /**
     * Response has expected structure.
     */
    public function test_recommendation_response_structure(): void
    {
        $user = User::factory()->create();

        Book::factory()->create([
            'is_published' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/recommendations');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'title',
                        'slug',
                    ],
                ],
            ]);
    }
}