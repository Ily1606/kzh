<?php

namespace Tests\Unit\Repositories;

use App\Contracts\StarRepositoryInterface;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\StarRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StarRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_repository_is_bound_to_its_contract(): void
    {
        $this->assertInstanceOf(StarRepository::class, app(StarRepositoryInterface::class));
    }

    public function test_starred_plugin_ids_excludes_plugins_without_a_row(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();

        $this->assertSame([], app(StarRepositoryInterface::class)->starredPluginIds([$plugin->id], $user->id));
    }

    /**
     * An empty page has nothing to resolve, so the repository must short-circuit
     * before it reaches the database.
     */
    public function test_starred_plugin_ids_returns_empty_without_querying_when_given_no_ids(): void
    {
        $user = User::factory()->create();

        DB::connection()->enableQueryLog();

        $result = app(StarRepositoryInterface::class)->starredPluginIds([], $user->id);

        $this->assertSame([], $result);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_starred_plugin_ids_returns_only_the_starred_plugins(): void
    {
        $user = User::factory()->create();
        $starred = Plugin::factory()->create();
        $notStarred = Plugin::factory()->create();
        $repository = app(StarRepositoryInterface::class);
        $repository->insertIgnore($starred->id, $user->id);

        $result = $repository->starredPluginIds([$starred->id, $notStarred->id], $user->id);

        $this->assertSame([$starred->id], $result);
    }

    public function test_starred_plugin_ids_only_covers_the_given_ids(): void
    {
        $user = User::factory()->create();
        $inPage = Plugin::factory()->create();
        $offPage = Plugin::factory()->create();
        $repository = app(StarRepositoryInterface::class);
        $repository->insertIgnore($inPage->id, $user->id);
        $repository->insertIgnore($offPage->id, $user->id);

        $result = $repository->starredPluginIds([$inPage->id], $user->id);

        $this->assertSame([$inPage->id], $result);
    }

    public function test_starred_plugin_ids_is_scoped_to_the_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $repository = app(StarRepositoryInterface::class);
        $repository->insertIgnore($plugin->id, $owner->id);

        $this->assertSame([], $repository->starredPluginIds([$plugin->id], $other->id));
    }

    public function test_insert_ignore_returns_true_on_first_insert(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();

        $this->assertTrue(app(StarRepositoryInterface::class)->insertIgnore($plugin->id, $user->id));

        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * The core of idempotent starring: the second attempt must insert nothing,
     * which is what lets the caller leave the counter alone.
     */
    public function test_insert_ignore_returns_false_when_the_pair_already_exists(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();
        $repository = app(StarRepositoryInterface::class);

        $this->assertTrue($repository->insertIgnore($plugin->id, $user->id));
        $this->assertFalse($repository->insertIgnore($plugin->id, $user->id));

        $this->assertDatabaseCount('stars', 1);
    }

    public function test_insert_ignore_is_scoped_to_the_user(): void
    {
        $plugin = Plugin::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $repository = app(StarRepositoryInterface::class);

        $this->assertTrue($repository->insertIgnore($plugin->id, $owner->id));
        $this->assertTrue($repository->insertIgnore($plugin->id, $other->id));

        $this->assertDatabaseCount('stars', 2);
    }

    public function test_delete_by_returns_true_when_a_row_is_removed(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();
        $repository = app(StarRepositoryInterface::class);
        $repository->insertIgnore($plugin->id, $user->id);

        $this->assertTrue($repository->deleteBy($plugin->id, $user->id));

        $this->assertDatabaseCount('stars', 0);
    }

    /**
     * Unstarring something that was never starred is a normal outcome, not a
     * missing resource: `false` here is what lets the endpoint answer 200 and
     * leave the counter alone.
     */
    public function test_delete_by_returns_false_when_nothing_matched(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse(app(StarRepositoryInterface::class)->deleteBy($plugin->id, $user->id));
    }

    public function test_delete_by_only_removes_the_calling_users_row(): void
    {
        $plugin = Plugin::factory()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $repository = app(StarRepositoryInterface::class);
        $repository->insertIgnore($plugin->id, $owner->id);
        $repository->insertIgnore($plugin->id, $other->id);

        $this->assertTrue($repository->deleteBy($plugin->id, $owner->id));

        $this->assertDatabaseMissing('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $other->id,
        ]);
    }
}
