<?php

namespace Tests\Unit\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\PluginRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(0, $plugin->stars()->count());
        // `comment_count` / `star_count` are aliases, never model attributes, so
        // they are deliberately absent from casts(): a fresh model has neither.
        $this->assertFalse($plugin->hasAttribute('comment_count'));
        $this->assertFalse($plugin->hasAttribute('star_count'));
        $this->assertIsInt($plugin->view_count);
        $this->assertTrue($plugin->user->is($user));
        $this->assertTrue($user->plugins->contains($plugin));
    }

    /**
     * No status filter, unlike findApprovedById(). A pending plugin still has an
     * owner who may correct it, so the write path must be able to load it.
     */
    public function test_find_by_id_returns_a_plugin_whatever_its_status(): void
    {
        $user = User::factory()->create();
        $repository = app(PluginRepositoryInterface::class);

        $pending = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Pending,
        ]);

        $this->assertTrue($repository->findById($pending->id)->is($pending));

        $approved = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->assertTrue($repository->findById($approved->id)->is($approved));

        // The contrast that makes the test meaningful.
        $this->expectException(ModelNotFoundException::class);
        $repository->findApprovedById($pending->id);
    }

    public function test_find_by_id_does_not_return_a_soft_deleted_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);
        $plugin->delete();

        $this->expectException(ModelNotFoundException::class);

        app(PluginRepositoryInterface::class)->findById($plugin->id);
    }

    public function test_update_writes_only_the_given_attributes(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        // Stars live in their own table; an update to the plugin must leave
        // the rows — and therefore the count — alone.
        User::factory()->count(7)->create()->each(function (User $stargazer) use ($plugin): void {
            DB::table('stars')->insert([
                'plugin_id' => $plugin->id,
                'user_id' => $stargazer->id,
            ]);
        });

        $updated = app(PluginRepositoryInterface::class)->update($plugin, [
            'title' => 'Debugbar mới',
        ]);

        $this->assertSame('Debugbar mới', $updated->title);

        // Everything not passed in survives, system-managed columns included.
        $this->assertSame('Laravel Debugbar', $updated->name);
        $this->assertSame('MIT', $updated->license);
        $this->assertSame(7, $updated->stars()->count());
        $this->assertSame(PluginStatus::Approved, $updated->status);
        $this->assertNotNull($updated->approved_at);

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'title' => 'Debugbar mới',
            'name' => 'Laravel Debugbar',
        ]);
        $this->assertDatabaseCount('stars', 7);
    }

    public function test_update_moves_updated_at(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'updated_at' => now()->subDays(5),
        ]);

        $before = $plugin->updated_at->copy();

        $updated = app(PluginRepositoryInterface::class)->update($plugin, [
            'title' => 'Edit mới',
        ]);

        // No counter update competes for this write: `star_count` and
        // `comment_count` are counts over their own tables, so an edit of the
        // content is the only thing that moves the plugin row, and the
        // timestamp has to follow it.
        $this->assertTrue($updated->updated_at->greaterThan($before));
    }
}
