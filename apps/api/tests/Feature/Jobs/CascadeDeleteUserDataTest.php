<?php

namespace Tests\Feature\Jobs;

use App\Enums\PluginStatus;
use App\Jobs\CascadeDeleteUserData;
use App\Mail\AccountDeletedMail;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\Star;
use App\Models\User;
use App\Services\StarService;
use App\Services\TrendingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
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
        app()->call([$job, 'handle']);

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
        app()->call([$job, 'handle']);

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
        app()->call([$job, 'handle']);

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
        app()->call([$job, 'handle']);

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

        TrendingTracker::bulkDecrementInteractions('comments', [
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
        app()->call([$job, 'handle']);

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
        app()->call([$job, 'handle']);

        $this->assertFalse((bool) Redis::sismember($activeKey, $plugin->id));
        $this->assertNull(Redis::get($statKey));
    }

    public function test_sends_account_deleted_email_to_user_upon_successful_deletion(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'deleted_user@example.com']);
        $user->delete();

        $job = new CascadeDeleteUserData($user);
        app()->call([$job, 'handle']);

        Mail::assertSent(AccountDeletedMail::class, function (AccountDeletedMail $mail) use ($user) {
            return $mail->hasTo('deleted_user@example.com')
                && $mail->user->id === $user->id;
        });
    }

    public function test_job_is_idempotent_on_retry_without_double_decrementing_redis_stats(): void
    {
        $user = User::factory()->create();
        $targetPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $today = now()->format('Y-m-d');

        // Create 2 comments and 1 star by user
        Comment::factory()->count(2)->create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $user->id,
            'created_at' => now(),
            'hidden_at' => null,
        ]);
        Star::create([
            'plugin_id' => $targetPlugin->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $commentStatKey = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $today, $targetPlugin->id);
        $starStatKey = TrendingTracker::statKey(TrendingTracker::INTERACTION_STARS, $today, $targetPlugin->id);

        Redis::set($commentStatKey, 10);
        Redis::set($starStatKey, 5);

        $user->delete();

        // Run 1: first job execution
        $job = new CascadeDeleteUserData($user);
        app()->call([$job, 'handle']);

        // Assert: decremented appropriately (10 - 2 = 8, 5 - 1 = 4)
        $this->assertEquals(8, (int) Redis::get($commentStatKey));
        $this->assertEquals(4, (int) Redis::get($starStatKey));

        // Run 2: retry job execution (simulating queue retry)
        app()->call([$job, 'handle']);

        // Assert: stats MUST NOT be decremented again
        $this->assertEquals(8, (int) Redis::get($commentStatKey));
        $this->assertEquals(4, (int) Redis::get($starStatKey));
    }

    public function test_chunked_processing_of_large_comment_volume_and_nested_threads(): void
    {
        // Set small chunk size to verify multi-chunk logic across all queries
        config(['plugins.cascade_delete.chunk_size' => 100]);

        $user = User::factory()->create();
        $pluginA = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);
        $pluginB = Plugin::factory()->create(['status' => PluginStatus::Approved, 'approved_at' => now()]);

        $today = now()->format('Y-m-d');
        $now = now();

        // 150 root comments by user on Plugin A
        $rootRecords = [];
        for ($i = 0; $i < 150; $i++) {
            $rootRecords[] = [
                'id' => (string) Str::uuid(),
                'plugin_id' => $pluginA->id,
                'author_id' => $user->id,
                'parent_comment_id' => null,
                'content' => "Root {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rootRecords, 50) as $chunk) {
            Comment::insert($chunk);
        }

        // 120 child replies on those root comments by other users on Plugin A
        $otherUser = User::factory()->create();
        $childRecords = [];
        for ($i = 0; $i < 120; $i++) {
            $childRecords[] = [
                'id' => (string) Str::uuid(),
                'plugin_id' => $pluginA->id,
                'author_id' => $otherUser->id,
                'parent_comment_id' => $rootRecords[$i]['id'],
                'content' => "Child {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($childRecords, 50) as $chunk) {
            Comment::insert($chunk);
        }

        // 50 root comments on Plugin B by user
        $pluginBRecords = [];
        for ($i = 0; $i < 50; $i++) {
            $pluginBRecords[] = [
                'id' => (string) Str::uuid(),
                'plugin_id' => $pluginB->id,
                'author_id' => $user->id,
                'parent_comment_id' => null,
                'content' => "Plugin B Comment {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($pluginBRecords, 50) as $chunk) {
            Comment::insert($chunk);
        }

        $statKeyA = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $today, $pluginA->id);
        $statKeyB = TrendingTracker::statKey(TrendingTracker::INTERACTION_COMMENTS, $today, $pluginB->id);
        Redis::set($statKeyA, 300);
        Redis::set($statKeyB, 100);

        $user->delete();

        $job = new CascadeDeleteUserData($user);
        app()->call([$job, 'handle']);

        // Assert: All comments (150 + 120 on A, 50 on B) must be soft deleted
        $this->assertEquals(0, Comment::whereIn('plugin_id', [$pluginA->id, $pluginB->id])->whereNull('deleted_at')->count());
        $this->assertEquals(320, Comment::onlyTrashed()->whereIn('plugin_id', [$pluginA->id, $pluginB->id])->count());

        // Assert: Redis stats decremented properly across chunks
        // Plugin A had 150 + 120 = 270 comments removed: 300 - 270 = 30
        $this->assertEquals(30, (int) Redis::get($statKeyA));
        // Plugin B had 50 comments removed: 100 - 50 = 50
        $this->assertEquals(50, (int) Redis::get($statKeyB));
    }

    public function test_cascade_delete_and_trending_tracker_tolerate_null_or_zero_chunk_size(): void
    {
        // When chunk_size is 0, null, or negative, both components must not throw ValueError
        foreach ([null, 0, -1] as $invalidChunkSize) {
            config(['plugins.cascade_delete.chunk_size' => $invalidChunkSize]);

            $user = User::factory()->create();
            $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'user_id' => $user->id]);
            Comment::factory()->create(['author_id' => $user->id, 'plugin_id' => $plugin->id]);

            // Test TrendingTracker::bulkDecrementInteractions
            TrendingTracker::bulkDecrementInteractions('comments', [
                ['date' => now()->format('Y-m-d'), 'plugin_id' => $plugin->id, 'count' => 1],
            ]);

            // Test TrendingTracker::cleanupPlugins
            TrendingTracker::cleanupPlugins([$plugin->id], deleteStats: true);

            // Test CascadeDeleteUserData job
            $user->delete();
            $job = new CascadeDeleteUserData($user);
            app()->call([$job, 'handle']);

            $this->assertTrue(true, 'Executed successfully without ValueError');
        }
    }

    public function test_cascade_delete_and_trending_tracker_clear_star_timeline_cache_via_star_service_key(): void
    {
        $user = User::factory()->create();
        $targetPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Star::create([
            'plugin_id' => $targetPlugin->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $timelineKey = StarService::timelineCacheKey($targetPlugin->id);
        Cache::put($timelineKey, ['timeline_data'], 900);
        $this->assertTrue(Cache::has($timelineKey));

        $user->delete();
        $job = new CascadeDeleteUserData($user);
        app()->call([$job, 'handle']);

        // Star timeline cache must have been forgotten
        $this->assertFalse(Cache::has($timelineKey));

        // Also verify TrendingTracker::cleanupPlugins clears it
        Cache::put($timelineKey, ['timeline_data_again'], 900);
        $this->assertTrue(Cache::has($timelineKey));
        TrendingTracker::cleanupPlugins([$targetPlugin->id]);
        $this->assertFalse(Cache::has($timelineKey));
    }
}
