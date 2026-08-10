<?php

namespace Database\Factories;

use App\Models\ReaderPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReaderPreference>
 */
class ReaderPreferenceFactory extends Factory
{
    protected $model = ReaderPreference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),

            'theme' => 'light',

            'font_size' => 18,

            'font_family' => 'serif',

            'line_spacing' => 1.60,

            'reading_mode' => 'paginated',
        ];
    }
}