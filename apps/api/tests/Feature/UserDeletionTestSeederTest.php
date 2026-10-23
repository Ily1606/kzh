<?php

namespace Tests\Feature;

use App\Jobs\CascadeDeleteUserData;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\PluginDailyView;
use App\Models\Star;
use App\Models\User;
use App\Services\TrendingTracker;
use Database\Seeders\UserDeletionTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UserDeletionTestSeederTest extends TestCase
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

    public function test_seeder_populates_full_dataset_and_deletion_cleans_it_up_completely(): void
    {
        // 1. Run Seeder
        $this->seed(UserDeletionTestSeeder::class);

        $targetUser = User::where('email', UserDeletionTestSeeder::TARGET_USER_EMAIL)->firstOrFail();
        $reviewer = User::where('email', UserDeletionTestSeeder::REVIEWER_USER_EMAIL)->firstOrFail();

        $pluginA = Plugin::where('name', 'target-user-tool')->firstOrFail();
        $pluginB = Plugin::where('name', 'target-user-draft')->firstOrFail();
        $pluginExternal = Plugin::where('name', 'external-community-plugin')->firstOrFail();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // Verify Initial State in Database
        $this->assertCount(2, $targetUser->tokens);
        $this->assertNotNull($targetUser->profile);
        $this->assertEquals(2, PluginDailyView::where('plugin_id', $pluginA->id)->count());
        $this->assertEquals(2, PluginDailyView::where('plugin_id', $pluginExternal->id)->count());
        $this->assertFalse($pluginA->trashed());
        $this->assertFalse($pluginB->trashed());
        $this->assertFalse($pluginExternal->trashed());

        // Verify Initial State in Redis
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');

        $this->assertNotNull(Redis::zscore($zsetKey, $pluginA->id));
        $this->assertNotNull(Redis::zscore($zsetKey, $pluginExternal->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $pluginA->id));
        $this->assertTrue((bool) Redis::hexists($hashKey, $pluginExternal->id));
        $this->assertTrue((bool) Redis::hexists($bufferKey, $pluginA->id));
        $this->assertTrue((bool) Redis::sismember(TrendingTracker::activeKey($today), $pluginA->id));

        // 2. Act: Delete target user and execute cascade delete job
        $targetUser->delete();
        $job = new CascadeDeleteUserData($targetUser);
        $job->handle();

        // 3. Assertions: Full cleanup verification

        // A. User and Tokens
        $this->assertTrue($targetUser->fresh()->trashed());
        $this->assertCount(0, $targetUser->fresh()->tokens);

        // B. Target User's Plugins & Related Comments/Stars
        $this->assertTrue($pluginA->fresh()->trashed());
        $this->assertTrue($pluginB->fresh()->trashed());
        $this->assertEquals(0, Comment::where('plugin_id', $pluginA->id)->count()); // all soft-deleted
        $this->assertEquals(0, Comment::where('plugin_id', $pluginB->id)->count());
        $this->assertEquals(0, Star::where('plugin_id', $pluginA->id)->count());

        // C. External Plugin - User's comments and child replies deleted, but independent comments intact
        $this->assertFalse($pluginExternal->fresh()->trashed());
        // Remaining visible comment should ONLY be reviewer's independent comment
        $remainingComments = Comment::where('plugin_id', $pluginExternal->id)->get();
        $this->assertCount(1, $remainingComments);
        $this->assertEquals($reviewer->id, $remainingComments->first()->author_id);
        $this->assertStringContainsString('Welcome everyone', $remainingComments->first()->content);

        // Remaining visible star should ONLY be reviewer's star
        $remainingStars = Star::where('plugin_id', $pluginExternal->id)->get();
        $this->assertCount(1, $remainingStars);
        $this->assertEquals($reviewer->id, $remainingStars->first()->user_id);

        // D. Redis Cleanup for Plugin A (owned by target user)
        $this->assertFalse((bool) Redis::zscore($zsetKey, $pluginA->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $pluginA->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $pluginA->id));
        $this->assertFalse((bool) Redis::sismember(TrendingTracker::activeKey($today), $pluginA->id));
        $this->assertNull(Redis::get(TrendingTracker::statKey('views', $today, $pluginA->id)));
        $this->assertNull(Redis::get(TrendingTracker::statKey('comments', $today, $pluginA->id)));
        $this->assertNull(Redis::get(TrendingTracker::statKey('stars', $yesterday, $pluginA->id)));

        // E. Redis Decrements for External Plugin
        // Both target user comment and the reply in that thread were deleted (2 decremented from 2 -> 0)
        $this->assertEquals(0, (int) Redis::get(TrendingTracker::statKey('comments', $today, $pluginExternal->id)));
        // Reviewer's independent comment yesterday was NOT deleted (remains 1)
        $this->assertEquals(1, (int) Redis::get(TrendingTracker::statKey('comments', $yesterday, $pluginExternal->id)));
        // Target user star was decremented (1 decremented from 1 -> 0)
        $this->assertEquals(0, (int) Redis::get(TrendingTracker::statKey('stars', $today, $pluginExternal->id)));
        // Reviewer's star yesterday was NOT deleted (remains 1)
        $this->assertEquals(1, (int) Redis::get(TrendingTracker::statKey('stars', $yesterday, $pluginExternal->id)));
        // External plugin remains in trending
        $this->assertNotNull(Redis::zscore($zsetKey, $pluginExternal->id));

        // Cached object in Redis refreshed with new counts (1 star, 1 comment)
        $cachedExtRaw = Redis::hget($hashKey, $pluginExternal->id);
        $this->assertNotEmpty($cachedExtRaw);
        $cachedExt = unserialize($cachedExtRaw);
        $this->assertEquals(1, $cachedExt->comment_count);
        $this->assertEquals(1, $cachedExt->star_count);
    }
}
