<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UsernameHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsernameHistory>
 */
class UsernameHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => fake()->userName(),
            'released_at' => now(),
            'available_at' => now()->addDays(30),
        ];
    }
}