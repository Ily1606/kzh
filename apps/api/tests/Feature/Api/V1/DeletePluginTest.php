<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeletePluginTest extends TestCase
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

    public function test_delete_requires_authentication(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.unauthenticated'));

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_by_non_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.unauthorized'));

        $this->assertDatabaseHas('plugins', [
            'id' => $plugin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_non_existent_plugin_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/plugins/00000000-0000-4000-8000-000000000000')
            ->assertNotFound();
    }

    public function test_delete_already_soft_deleted_plugin_returns_404(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);
        $plugin->delete();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertNotFound();
    }

    public function test_owner_can_delete_their_plugin_successfully(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => __('api.plugin_deleted_successfully'),
                'data' => null,
                'errors' => null,
            ]);

        $this->assertSoftDeleted('plugins', [
            'id' => $plugin->id,
        ]);
    }

    public function test_delete_plugin_cascades_soft_delete_to_comments_stars_and_reports(): void
    {
        $owner = User::factory()->create();
        $stargazer = User::factory()->create();
        $commenter = User::factory()->create();

        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $commenter->id,
        ]);

        $report = CommentReport::create([
            'plugin_id' => $plugin->id,
            'comment_id' => $comment->id,
            'user_id' => $stargazer->id,
            'reason' => 'Spam comment',
        ]);

        $star = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $stargazer->id,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk();

        $this->assertSoftDeleted('plugins', ['id' => $plugin->id]);
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertSoftDeleted('comment_reports', ['id' => $report->id]);
        $this->assertSoftDeleted('stars', [
            'plugin_id' => $plugin->id,
            'user_id' => $stargazer->id,
        ]);
    }

    public function test_delete_plugin_cleans_up_redis_trending_caches_and_daily_stats(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $processingKey = $bufferKey.'_processing';
        $today = now()->format('Y-m-d');
        $activeKey = TrendingTracker::activeKey($today);
        $viewsStatKey = TrendingTracker::statKey('views', $today, $plugin->id);
        $commentsStatKey = TrendingTracker::statKey('comments', $today, $plugin->id);
        $starsStatKey = TrendingTracker::statKey('stars', $today, $plugin->id);

        Redis::zadd($zsetKey, 150, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 10);
        Redis::hset($processingKey, $plugin->id, 5);
        Redis::sadd($activeKey, $plugin->id);
        Redis::set($viewsStatKey, 10);
        Redis::set($commentsStatKey, 3);
        Redis::set($starsStatKey, 2);

        $timelineKey1 = "plugin:star_timeline:{$plugin->id}";
        $timelineKey2 = "plugins:{$plugin->id}:star_timeline";
        Cache::put($timelineKey1, ['timeline_data'], 900);
        Cache::put($timelineKey2, ['timeline_data'], 900);

        $this->assertEquals(150, Redis::zscore($zsetKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertTrue((bool) Redis::hexists($processingKey, $plugin->id));
        $this->assertTrue((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertEquals('10', Redis::get($viewsStatKey));
        $this->assertTrue(Cache::has($timelineKey1));
        $this->assertTrue(Cache::has($timelineKey2));

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk();

        // Verify Redis cleanup
        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($processingKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertNull(Redis::get($viewsStatKey));
        $this->assertNull(Redis::get($commentsStatKey));
        $this->assertNull(Redis::get($starsStatKey));

        // Verify Cache cleanup
        $this->assertFalse(Cache::has($timelineKey1));
        $this->assertFalse(Cache::has($timelineKey2));
    }
}
