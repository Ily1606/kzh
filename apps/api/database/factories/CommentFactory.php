<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plugin_id' => Plugin::factory(),
            'author_id' => User::factory(),
            'parent_comment_id' => null,
            'content' => fake()->paragraph(),
            'hidden_at' => null,
        ];
    }

    /**
     * Mark the comment as hidden (moderated).
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'hidden_at' => now(),
        ]);
    }

    /**
     * Attach the comment as a direct reply of $parent.
     */
    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'plugin_id' => $parent->plugin_id,
            'parent_comment_id' => $parent->id,
        ]);
    }
}
