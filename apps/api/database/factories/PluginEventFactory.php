<?php

namespace Database\Factories;

use App\Enums\PluginEventType;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PluginEvent>
 */
class PluginEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plugin_id' => Plugin::factory(),
            'admin_id' => null,
            'event_type' => PluginEventType::Created,
            'message' => null,
        ];
    }

    /**
     * The owner's own submission, which carries no admin text.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => PluginEventType::Created,
            'admin_id' => null,
            'message' => null,
        ]);
    }

    /**
     * An owner resubmission, which also carries no admin text.
     */
    public function resubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => PluginEventType::Resubmitted,
            'admin_id' => null,
            'message' => null,
        ]);
    }

    /**
     * A reviewer's decision, with the text they wrote for the owner.
     */
    public function byAdmin(User $admin, PluginEventType $eventType, ?string $message = null): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $eventType,
            'admin_id' => $admin->id,
            'message' => $message,
        ]);
    }
}
