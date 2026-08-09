<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookEntitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookEntitlementFactory extends Factory
{
    protected $model = BookEntitlement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),

            'source' => 'purchase',

            'can_read' => true,

            'can_download' => true,

            'status' => 'active',

            'granted_at' => now(),

            'expires_at' => null,

            'revoked_at' => null,
        ];
    }
}