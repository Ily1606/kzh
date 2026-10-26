<?php

namespace Tests\Feature;

use App\Jobs\CascadeDeletePluginData;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\PluginDailyView;
use App\Models\Star;
use App\Services\TrendingTracker;
use Database\Seeders\PluginDeletionTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PluginDeletionTestSeederTest extends TestCase
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
        Cache::flush();
        parent::tearDown();
    }

    public function test_seeder_populates_full_dataset_and_plugin_deletion_cleans_it_up_completely(): void
    {
        // 1. Run Seeder
        $this->seed(PluginDeletionTestSeeder::class);

        $targetPlugin = Plugin::where('name', 'target-plugin-to-delete')->firstOrFail();
        $draftPlugin = Plugin::where('name', 'target-plugin-draft')->firstOrFail();
        $otherPlugin = Plugin::where('name', 'other-community-plugin')->firstOrFail();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // Verify Initial State in Database
        $this->assertFalse($targetPlugin->trashed());
        $this->assertFalse($draftPlugin->trashed());
        $this->assertFalse($otherPlugin->trashed());
        $this->assertEquals(2, PluginDailyView::where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(2, PluginDailyView::where('plugin_id', $otherPlugin->id)->count());
        $this->assertEquals(3, Comment::where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(1, CommentReport::where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(1, Star::where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(1, Comment::where('plugin_id', $otherPlugin->id)->count());
        $this->assertEquals(1, Star::where('plugin_id', $otherPlugin->id)->count());

        // Verify Initial State in Redis & Cache
        $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
        $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
        $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');
        $processingKey = $bufferKey.'_processing';

        $this->assertNotNull(Redis::zscore($zsetKey, $targetPlugin->id));
        $this->assertNotNull(Redis::zscore($zsetKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $targetPlugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $targetPlugin->id));
        $this->assertTrue((bool) Redis::hexists($processingKey, $targetPlugin->id));
        $this->assertTrue((bool) Redis::sismember(TrendingTracker::activeKey($today), $targetPlugin->id));
        $this->assertTrue((bool) Redis::sismember(TrendingTracker::activeKey($today), $otherPlugin->id));

        $this->assertNotNull(Cache::get("plugin:star_timeline:{$targetPlugin->id}"));
        $this->assertNotNull(Cache::get("plugins:{$targetPlugin->id}:star_timeline"));
        $this->assertNotNull(Cache::get("plugin:star_timeline:{$otherPlugin->id}"));

        // 2. Act: Soft-delete target plugin and execute cascade delete job
        $targetPlugin->delete();
        $job = new CascadeDeletePluginData($targetPlugin);
        app()->call([$job, 'handle']);

        // 3. Assertions: Full cleanup verification

        // A. Target Plugin & Related Database Records
        $this->assertTrue($targetPlugin->fresh()->trashed());
        $this->assertEquals(0, Comment::where('plugin_id', $targetPlugin->id)->count()); // all 3 soft-deleted
        $this->assertEquals(0, CommentReport::where('plugin_id', $targetPlugin->id)->count()); // soft-deleted
        $this->assertEquals(0, Star::where('plugin_id', $targetPlugin->id)->count()); // soft-deleted

        // Soft-deleted records still exist in withTrashed()
        $this->assertEquals(3, Comment::withTrashed()->where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(1, CommentReport::withTrashed()->where('plugin_id', $targetPlugin->id)->count());
        $this->assertEquals(1, Star::withTrashed()->where('plugin_id', $targetPlugin->id)->count());

        // B. Target Plugin Redis Cleanup
        $this->assertFalse((bool) Redis::zscore($zsetKey, $targetPlugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $targetPlugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $targetPlugin->id));
        $this->assertFalse((bool) Redis::hexists($processingKey, $targetPlugin->id));
        $this->assertFalse((bool) Redis::sismember(TrendingTracker::activeKey($today), $targetPlugin->id));
        $this->assertFalse((bool) Redis::sismember(TrendingTracker::activeKey($yesterday), $targetPlugin->id));

        $this->assertNull(Redis::get(TrendingTracker::statKey('views', $today, $targetPlugin->id)));
        $this->assertNull(Redis::get(TrendingTracker::statKey('views', $yesterday, $targetPlugin->id)));
        $this->assertNull(Redis::get(TrendingTracker::statKey('comments', $today, $targetPlugin->id)));
        $this->assertNull(Redis::get(TrendingTracker::statKey('stars', $today, $targetPlugin->id)));

        // C. Target Plugin Timeline Cache Cleared
        $this->assertNull(Cache::get("plugin:star_timeline:{$targetPlugin->id}"));
        $this->assertNull(Cache::get("plugins:{$targetPlugin->id}:star_timeline"));

        // D. Preservation of Other Plugin and Draft Plugin
        $this->assertFalse($draftPlugin->fresh()->trashed());
        $this->assertFalse($otherPlugin->fresh()->trashed());

        // Other plugin's database interactions remain intact
        $this->assertEquals(1, Comment::where('plugin_id', $otherPlugin->id)->count());
        $this->assertEquals(1, Star::where('plugin_id', $otherPlugin->id)->count());

        // Other plugin remains in trending and Redis caches
        $this->assertNotNull(Redis::zscore($zsetKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::hexists($processingKey, $otherPlugin->id));
        $this->assertTrue((bool) Redis::sismember(TrendingTracker::activeKey($today), $otherPlugin->id));
        $this->assertEquals(200, (int) Redis::get(TrendingTracker::statKey('views', $today, $otherPlugin->id)));
        $this->assertEquals(1, (int) Redis::get(TrendingTracker::statKey('comments', $yesterday, $otherPlugin->id)));
        $this->assertEquals(1, (int) Redis::get(TrendingTracker::statKey('stars', $yesterday, $otherPlugin->id)));

        // Other plugin timeline cache preserved
        $this->assertNotNull(Cache::get("plugin:star_timeline:{$otherPlugin->id}"));
    }
}

