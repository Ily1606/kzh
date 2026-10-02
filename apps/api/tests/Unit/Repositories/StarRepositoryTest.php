<?php

namespace Tests\Unit\Repositories;

use App\Contracts\StarRepositoryInterface;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\StarRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_repository_is_bound_to_its_contract(): void
    {
        $this->assertInstanceOf(StarRepository::class, app(StarRepositoryInterface::class));
    }

    public function test_is_starred_is_false_when_no_row_exists(): void
    {
        $plugin = Plugin::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse(app(StarRepositoryInterface::class)->isStarred($plugin->id, $user->id));
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
