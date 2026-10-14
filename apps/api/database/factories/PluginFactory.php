<?php

namespace Database\Factories;

use App\Enums\PluginLicense;
use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'name' => fake()->unique()->words(2, true),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'license' => PluginLicense::MIT->value,
            'approved_at' => null,
            'status' => PluginStatus::Pending,
            'source_link' => 'https://example.com/'.fake()->slug(2),
            'view_count' => 0,
        ];
    }

    /**
     * Put the plugin in a category, the way a submission does.
     *
     * The pivot cannot be written through `create()` because the category is
     * resolved first. Passing a name reuses the row a config-driven submission
     * would already have created.
     */
    public function withCategory(Category|string $category): static
    {
        return $this->afterCreating(function (Plugin $plugin) use ($category): void {
            $resolved = $category instanceof Category
                ? $category
                : Category::query()->firstOrCreate(['name' => $category]);

            $plugin->categories()->sync([$resolved->id]);
        });
    }

    /**
     * @param  array<int, Tag|string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->afterCreating(function (Plugin $plugin) use ($tags): void {
            $ids = [];

            foreach ($tags as $tag) {
                $ids[] = ($tag instanceof Tag ? $tag : Tag::query()->firstOrCreate(['name' => $tag]))->id;
            }

            $plugin->tags()->sync($ids);
        });
    }
}
