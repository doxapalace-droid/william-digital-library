<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),

            'user_id' => User::factory(),

            'order_number' =>
                'DP-' . strtoupper(
                    Str::random(10)
                ),

            'status' => 'pending',

            'payment_status' => 'unpaid',

            'currency' => 'GHS',

            'subtotal' => 100.00,

            'discount' => 0.00,

            'total' => 100.00,

            'paid_at' => null,
        ];
    }
}