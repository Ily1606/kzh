<?php

namespace Database\Factories;

use App\Enums\PluginLicense;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->unique()->words(2, true),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'license' => PluginLicense::MIT->value,
            'approved_at' => null,
            'status' => PluginStatus::Pending,
            'source_link' => 'https://example.com/'.fake()->slug(2),
            'star_count' => 0,
            'comment_count' => 0,
            'view_count' => 0,
        ];
    }
}
