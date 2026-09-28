<?php

namespace Tests\Unit\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\PluginRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_repository_is_bound_to_its_contract(): void
    {
        $this->assertInstanceOf(PluginRepository::class, app(PluginRepositoryInterface::class));
    }

    public function test_plugin_repository_uses_plugin_model(): void
    {
        $this->assertSame(Plugin::class, app(PluginRepository::class)->getModel());
        $this->assertInstanceOf(Plugin::class, app(PluginRepository::class)->resetModel());
    }

    public function test_plugin_repository_creates_plugins(): void
    {
        $user = User::factory()->create();

        $plugin = app(PluginRepositoryInterface::class)->create([
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'source_link' => 'https://github.com/barryvdh/laravel-debugbar',
            'status' => PluginStatus::Pending,
            'star_count' => 0,
            'comment_count' => 0,
            'view_count' => 0,
        ]);

        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertDatabaseHas('plugins', [

            'id' => $plugin->id,
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
        ]);
    }

    public function test_plugin_model_casts_status_and_relations(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $this->assertSame(PluginStatus::Pending, $plugin->status);
        $this->assertIsInt($plugin->star_count);
        $this->assertIsInt($plugin->comment_count);
        $this->assertIsInt($plugin->view_count);
        $this->assertTrue($plugin->user->is($user));
        $this->assertTrue($user->plugins->contains($plugin));
    }
}
