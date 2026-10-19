<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CommentObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    public function test_trending_tracker_clamp_cannot_be_negative(): void
    {
        $plugin = Plugin::factory()->create();
        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";

        // Decrement when key does not exist yet
        TrendingTracker::trackComment($plugin->id, -1);
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Increment to 2, then decrement by 5 -> should clamp at 0
        TrendingTracker::trackComment($plugin->id, 2);
        $this->assertEquals(2, (int) Redis::get($statKey));

        TrendingTracker::trackComment($plugin->id, -5);
        $this->assertEquals(0, (int) Redis::get($statKey));
    }

    public function test_creating_comment_increments_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Initially cache the plugin with comment_count = 0
        $plugin->comment_count = 0;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Act: create comment via model
        Comment::create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'content' => 'Great plugin!',
        ]);

        // 1. Trending stats in Redis should be incremented to 1
        $this->assertEquals(1, (int) Redis::get($statKey));

        // 2. Cached plugin object in Redis should have comment_count updated to 1
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(1, $cachedPlugin->comment_count);
    }

    public function test_deleting_comment_decrements_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Set up initial trending interaction and cached plugin with comment_count = 1
        Redis::set($statKey, 1);
        $plugin->comment_count = 1;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Soft delete the comment
        $comment->delete();

        // 1. Trending stats in Redis should be decremented to 0
        $this->assertEquals(0, (int) Redis::get($statKey));

        // 2. Cached plugin object in Redis should have comment_count updated to 0
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(0, $cachedPlugin->comment_count);
    }

    public function test_force_deleting_comment_decrements_trending_stats(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        Redis::set($statKey, 2);

        // Force delete
        $comment->forceDelete();

        $this->assertEquals(1, (int) Redis::get($statKey));
    }

    public function test_restoring_comment_increments_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Initial setup
        Redis::set($statKey, 1);
        $plugin->comment_count = 1;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Soft delete the comment -> stat becomes 0, cached comment_count becomes 0
        $comment->delete();
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Restore the comment -> stat becomes 1, cached comment_count becomes 1
        $comment->restore();

        $this->assertEquals(1, (int) Redis::get($statKey));

        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(1, $cachedPlugin->comment_count);
    }

    public function test_hiding_comment_decrements_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'hidden_at' => null,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        // Set up initial trending interaction and cached plugin with comment_count = 1
        Redis::set($statKey, 1);
        $plugin->comment_count = 1;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Act: Hide the comment
        $comment->update(['hidden_at' => now()]);

        // 1. Trending stats in Redis should be decremented to 0
        $this->assertEquals(0, (int) Redis::get($statKey));

        // 2. Cached plugin object in Redis should have comment_count updated to 0
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(0, $cachedPlugin->comment_count);
    }

    public function test_unhiding_comment_increments_trending_stats_and_updates_cached_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'hidden_at' => now(),
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $hashKey = config('plugins.trending.keys.objects');
        $zsetKey = config('plugins.trending.keys.zset');

        Redis::set($statKey, 0);
        $plugin->comment_count = 0;
        Redis::zadd($zsetKey, 100, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));

        // Act: Unhide the comment
        $comment->update(['hidden_at' => null]);

        // 1. Trending stats in Redis should be incremented to 1
        $this->assertEquals(1, (int) Redis::get($statKey));

        // 2. Cached plugin object in Redis should have comment_count updated to 1
        $cachedRaw = Redis::hget($hashKey, $plugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedPlugin = unserialize($cachedRaw);
        $this->assertEquals(1, $cachedPlugin->comment_count);
    }

    public function test_deleting_hidden_comment_does_not_decrement_trending_stats_again(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'hidden_at' => null,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";

        // Initial comment creation set stat to 1. Now hide comment -> decrements to 0.
        $comment->update(['hidden_at' => now()]);
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Set stat key to 2 to clearly observe if delete decrements
        Redis::set($statKey, 2);

        // Act: Delete the hidden comment
        $comment->delete();

        // Assert: Stat should remain 2, not decremented to 1
        $this->assertEquals(2, (int) Redis::get($statKey));
    }

    public function test_force_deleting_already_soft_deleted_comment_does_not_decrement_trending_stats_again(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";

        // Soft delete the comment -> decrements stat from 1 to 0
        $comment->delete();
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Set stat to 2 to clearly observe if force-delete decrements
        Redis::set($statKey, 2);

        // Act: Force delete the already-trashed comment
        $comment->forceDelete();

        // Assert: Stat should remain 2, not decremented to 1
        $this->assertEquals(2, (int) Redis::get($statKey));
    }

    public function test_restoring_hidden_comment_does_not_increment_trending_stats(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'hidden_at' => now(),
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";

        // Since it was created hidden, stat is 0
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Soft delete the hidden comment
        $comment->delete();
        $this->assertEquals(0, (int) Redis::get($statKey));

        // Set stat to 1
        Redis::set($statKey, 1);

        // Act: Restore the comment while it is still hidden
        $comment->restore();

        // Assert: Stat should remain 1, not incremented to 2
        $this->assertEquals(1, (int) Redis::get($statKey));
    }

    public function test_deleting_old_comment_decrements_its_creation_date_bucket_not_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $pastDate = now()->subDays(5);
        $pastDateStr = $pastDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'created_at' => $pastDate,
        ]);

        $pastStatKey = "trending:stats:comments:{$pastDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:comments:{$todayStr}:{$plugin->id}";

        Redis::set($pastStatKey, 2);
        Redis::set($todayStatKey, 6);

        // Act: Delete comment created 5 days ago
        $comment->delete();

        // Past bucket should be decremented to 1
        $this->assertEquals(1, (int) Redis::get($pastStatKey));
        // Today's bucket must remain untouched at 6
        $this->assertEquals(6, (int) Redis::get($todayStatKey));
    }

    public function test_hiding_and_unhiding_old_comment_updates_its_creation_date_bucket_not_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $pastDate = now()->subDays(5);
        $pastDateStr = $pastDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'hidden_at' => null,
            'created_at' => $pastDate,
        ]);

        $pastStatKey = "trending:stats:comments:{$pastDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:comments:{$todayStr}:{$plugin->id}";

        Redis::set($pastStatKey, 2);
        Redis::set($todayStatKey, 6);

        // Act 1: Hide old comment
        $comment->update(['hidden_at' => now()]);

        // Past bucket decremented, today's bucket untouched
        $this->assertEquals(1, (int) Redis::get($pastStatKey));
        $this->assertEquals(6, (int) Redis::get($todayStatKey));

        // Act 2: Unhide old comment
        $comment->update(['hidden_at' => null]);

        // Past bucket re-incremented, today's bucket untouched
        $this->assertEquals(2, (int) Redis::get($pastStatKey));
        $this->assertEquals(6, (int) Redis::get($todayStatKey));
    }

    public function test_deleting_comment_outside_trending_window_ignores_decrement_and_does_not_affect_today(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $expiredDate = now()->subDays(40);
        $expiredDateStr = $expiredDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
            'created_at' => $expiredDate,
        ]);

        $expiredStatKey = "trending:stats:comments:{$expiredDateStr}:{$plugin->id}";
        $todayStatKey = "trending:stats:comments:{$todayStr}:{$plugin->id}";

        Redis::del($expiredStatKey);
        Redis::set($todayStatKey, 5);

        // Act: Delete comment outside trending window
        $comment->delete();

        // Expired key is not recreated
        $this->assertFalse((bool) Redis::exists($expiredStatKey));
        // Today's bucket remains 5
        $this->assertEquals(5, (int) Redis::get($todayStatKey));
    }
}

