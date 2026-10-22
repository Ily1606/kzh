<?php

namespace Tests\Feature\Jobs;

use App\Enums\PluginStatus;
use App\Jobs\CascadeDeleteUserData;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class CascadeDeleteUserDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_job_handles_non_existent_user_gracefully(): void
    {
        $nonExistentId = (string) Str::uuid();

        $job = new CascadeDeleteUserData($nonExistentId);
        $job->handle();

        $this->assertTrue(true);
    }

    public function test_job_does_not_delete_data_if_user_is_not_trashed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('active-token')->plainTextToken;
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $job = new CascadeDeleteUserData($user);
        $job->handle();

        $this->assertCount(1, $user->fresh()->tokens);
        $this->assertFalse($plugin->fresh()->trashed());
    }

    public function test_job_deletes_tokens_plugins_comments_and_stars_when_user_is_trashed(): void
    {
        $user = User::factory()->create();
        $user->createToken('active-token');

        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $otherUser = User::factory()->create();
        $otherPlugin = Plugin::factory()->create([
            'user_id' => $otherUser->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $otherPlugin->id,
            'author_id' => $user->id,
        ]);

        $star = Star::create([
            'plugin_id' => $otherPlugin->id,
            'user_id' => $user->id,
        ]);

        $commentOnUserPlugin = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $otherUser->id,
        ]);

        $starOnUserPlugin = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $otherUser->id,
        ]);

        // Soft delete user directly without observer cascading (by temporarily unobserving or soft deleting user model)
        // Since UserObserver now only dispatches CascadeDeleteUserData, deleting here dispatches or we can just delete
        $user->delete();

        // Run the job manually
        $job = new CascadeDeleteUserData($user);
        $job->handle();

        $this->assertCount(0, $user->fresh()->tokens);
        $this->assertTrue($plugin->fresh()->trashed());
        $this->assertTrue($comment->fresh()->trashed());
        $this->assertTrue($star->fresh()->trashed());
        $this->assertTrue($commentOnUserPlugin->fresh()->trashed());
        $this->assertTrue($starOnUserPlugin->fresh()->trashed());
    }

    public function test_bulk_decrement_and_cache_refresh_with_multiple_interactions(): void
    {
        $user = User::factory()->create();
        $targetPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // Create 2 visible comments today
        Comment::factory()->create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $user->id,
            'created_at' => now(),
            'hidden_at' => null,
        ]);
        Comment::factory()->create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $user->id,
            'created_at' => now(),
            'hidden_at' => null,
        ]);

        // Create 1 visible comment yesterday
        Comment::factory()->create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $user->id,
            'created_at' => now()->subDay(),
            'hidden_at' => null,
        ]);

        // Create 1 hidden comment today (already subtracted when hidden)
        Comment::factory()->create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $user->id,
            'created_at' => now(),
            'hidden_at' => now(),
        ]);

        // Setup Redis trending stats
        $statKeyToday = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $today, $targetPlugin->id);
        $statKeyYesterday = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $yesterday, $targetPlugin->id);
        Redis::set($statKeyToday, 5);
        Redis::set($statKeyYesterday, 3);

        $hashKey = config('plugins.trending.keys.objects');
        $targetPlugin->comment_count = 3;
        $targetPlugin->star_count = 0;
        Redis::hset($hashKey, $targetPlugin->id, serialize($targetPlugin));

        // Act: soft delete user and run cascade delete job
        $user->delete();

        $job = new CascadeDeleteUserData($user);
        $job->handle();

        // Assert: stats today reduced by 2 (from 5 to 3), hidden comment was not subtracted again
        $this->assertEquals(3, (int) Redis::get($statKeyToday));

        // Assert: stats yesterday reduced by 1 (from 3 to 2)
        $this->assertEquals(2, (int) Redis::get($statKeyYesterday));

        // Assert: cached object in Redis updated to reflect remaining visible comments (0)
        $cachedRaw = Redis::hget($hashKey, $targetPlugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(0, $cachedPlugin->comment_count);
    }

    public function test_bulk_decrement_preserves_existing_key_ttl(): void
    {
        $today = now()->format('Y-m-d');
        $statKey = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $today, 'test-plugin-id');

        // Set key with a specific TTL (500 seconds)
        Redis::setex($statKey, 500, 10);

        \App\Services\TrendingTracker::bulkDecrementInteractions('comments', [
            ['plugin_id' => 'test-plugin-id', 'date' => $today, 'count' => 3],
        ]);

        $this->assertEquals(7, (int) Redis::get($statKey));
        $remainingTtl = Redis::ttl($statKey);

        // TTL must be preserved (~500s) and not reset to 31 days (> 2,000,000s)
        $this->assertGreaterThan(450, $remainingTtl);
        $this->assertLessThanOrEqual(500, $remainingTtl);
    }

    public function test_child_comments_of_user_comments_are_cascade_soft_deleted(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $pluginAuthor = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $pluginAuthor->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // 1. User A posts a root comment
        $rootComment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userA->id,
            'parent_comment_id' => null,
        ]);

        // 2. User B posts a reply (child comment) to User A's comment
        $childComment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userB->id,
            'parent_comment_id' => $rootComment->id,
        ]);

        // 3. Another user posts a sub-reply (child of child)
        $subReply = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $userB->id,
            'parent_comment_id' => $childComment->id,
        ]);

        $this->assertEquals(3, Comment::where('plugin_id', $plugin->id)->count());

        // Act: soft delete User A and run cascade delete job
        $userA->delete();
        $job = new CascadeDeleteUserData($userA);
        $job->handle();

        // Assert: Root comment, child comment, and sub-reply are all soft-deleted
        $this->assertTrue($rootComment->fresh()->trashed());
        $this->assertTrue($childComment->fresh()->trashed());
        $this->assertTrue($subReply->fresh()->trashed());

        // Comment count for this plugin in DB must be 0
        $this->assertEquals(0, Comment::where('plugin_id', $plugin->id)->count());
    }

    public function test_cleanup_respects_custom_configured_redis_keys_and_prefixes(): void
    {
        config([
            'plugins.trending.keys.active_prefix' => 'custom:active_plugins',
            'plugins.trending.keys.stats_prefix' => 'custom:stats',
        ]);

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $today = now()->format('Y-m-d');
        $activeKey = TrendingTracker::activeKey($today);
        $statKey = TrendingTracker::statKey(TrendingTracker::INTERACTION_VIEWS, $today, $plugin->id);

        $this->assertEquals("custom:active_plugins:{$today}", $activeKey);
        $this->assertEquals("custom:stats:views:{$today}:{$plugin->id}", $statKey);

        Redis::sadd($activeKey, $plugin->id);
        Redis::set($statKey, 10);

        $user->delete();
        $job = new CascadeDeleteUserData($user);
        $job->handle();

        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertNull(Redis::get($statKey));
    }
}

