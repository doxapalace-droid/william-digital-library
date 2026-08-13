<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),

            'uuid' => (string) Str::uuid(),

            'gateway' => 'paystack',

            'status' => 'pending',

            'transaction_reference' => null,

            'currency' => 'GHS',

            'amount' => 100.00,

            'gateway_response' => null,

            'paid_at' => null,

            'failed_at' => null,
        ];
    }

    /**
     * Indicate that the payment was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'successful',

            'transaction_reference' =>
                'DP-' . strtoupper(Str::random(16)),

            'gateway_response' => json_encode([
                'status' => true,
                'message' => 'Payment successful',
            ]),

            'paid_at' => now(),

            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'transaction_reference' => null,
            'gateway_response' => null,
            'paid_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',

            'gateway_response' => json_encode([
                'status' => false,
                'message' => 'Payment failed',
            ]),

            'paid_at' => null,

            'failed_at' => now(),
        ]);
    }
}