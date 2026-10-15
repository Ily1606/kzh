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
}

