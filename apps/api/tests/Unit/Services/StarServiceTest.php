<?php

namespace Tests\Unit\Services;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Services\StarService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'star_count' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    // Starring
    // -----------------------------------------------------------------------

    public function test_star_creates_the_row_and_increments_the_counter(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        $result = app(StarService::class)->setStarred($user, $plugin->id, true);

        $this->assertTrue($result['starred']);
        $this->assertSame(1, $result['star_count']);
        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * The property the whole feature rests on: the client may repeat the
     * request freely (timeout retry, double tap) and the result must not drift.
     */
    public function test_starring_twice_increments_the_counter_only_once(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);

        $first = $service->setStarred($user, $plugin->id, true);
        $second = $service->setStarred($user, $plugin->id, true);

        $this->assertSame(1, $first['star_count']);
        $this->assertSame(1, $second['star_count']);
        $this->assertTrue($second['starred']);
        $this->assertDatabaseCount('stars', 1);
    }

    // -----------------------------------------------------------------------
    // Unstarring
    // -----------------------------------------------------------------------

    public function test_unstar_removes_the_row_and_decrements_the_counter(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);
        $service->setStarred($user, $plugin->id, true);

        $result = $service->setStarred($user, $plugin->id, false);

        $this->assertFalse($result['starred']);
        $this->assertSame(0, $result['star_count']);
        $this->assertDatabaseCount('stars', 0);
    }

    public function test_unstarring_a_plugin_that_was_never_starred_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        $result = app(StarService::class)->setStarred($user, $plugin->id, false);

        $this->assertFalse($result['starred']);
        $this->assertSame(0, $result['star_count']);
        $this->assertDatabaseCount('stars', 0);
    }

    public function test_unstarring_twice_decrements_the_counter_only_once(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);
        $service->setStarred($user, $plugin->id, true);

        $service->setStarred($user, $plugin->id, false);
        $result = $service->setStarred($user, $plugin->id, false);

        $this->assertSame(0, $result['star_count']);
    }

    public function test_the_counter_never_goes_below_zero(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);

        $service->setStarred($user, $plugin->id, true);
        $service->setStarred($user, $plugin->id, false);
        $service->setStarred($user, $plugin->id, false);
        $result = $service->setStarred($user, $plugin->id, false);

        $this->assertSame(0, $result['star_count']);
    }

    public function test_one_users_unstar_does_not_touch_another_users_star(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);
        $service->setStarred($owner, $plugin->id, true);

        $result = $service->setStarred($other, $plugin->id, false);

        $this->assertSame(1, $result['star_count']);
        $this->assertDatabaseHas('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $owner->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Response accuracy
    // -----------------------------------------------------------------------

    public function test_the_response_reflects_the_count_after_the_write(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);

        $service->setStarred($first, $plugin->id, true);
        $result = $service->setStarred($second, $plugin->id, true);

        $this->assertSame(2, $result['star_count']);
    }

    // -----------------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------------

    public function test_throws_not_found_for_a_pending_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->expectException(ModelNotFoundException::class);

        app(StarService::class)->setStarred($user, $plugin->id, true);
    }

    // -----------------------------------------------------------------------
    // getStarredState
    // -----------------------------------------------------------------------

    public function test_get_starred_state_reflects_the_stored_row(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);
        $service->setStarred($user, $plugin->id, true);

        $this->assertTrue($service->getStarredState($user, $plugin->id));
    }

    public function test_get_starred_state_is_false_without_a_row(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        $this->assertFalse(app(StarService::class)->getStarredState($user, $plugin->id));
    }

    public function test_get_starred_state_is_scoped_to_the_calling_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $service = app(StarService::class);
        $service->setStarred($owner, $plugin->id, true);

        $this->assertFalse($service->getStarredState($other, $plugin->id));
    }

    public function test_get_starred_state_throws_not_found_for_a_pending_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->expectException(ModelNotFoundException::class);

        app(StarService::class)->getStarredState($user, $plugin->id);
    }
}
