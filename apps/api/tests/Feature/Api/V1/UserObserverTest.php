<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Jobs\CascadeDeleteUserData;
use App\Mail\AccountLockedMail;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UserObserverTest extends TestCase
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

    public function test_soft_deleting_user_cascades_tokens_plugins_comments_and_stars(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $this->assertCount(1, $user->tokens);

        // User's own plugin
        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // Another plugin created by someone else
        $otherUser = User::factory()->create();
        $otherPlugin = Plugin::factory()->create([
            'user_id' => $otherUser->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // User comments on otherPlugin
        $comment = Comment::factory()->create([
            'plugin_id' => $otherPlugin->id,
            'author_id' => $user->id,
        ]);

        // User stars otherPlugin
        $star = Star::create([
            'plugin_id' => $otherPlugin->id,
            'user_id' => $user->id,
        ]);

        // Another user comments and stars on user's own plugin
        $commentOnUserPlugin = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $otherUser->id,
        ]);
        $starOnUserPlugin = Star::create([
            'plugin_id' => $plugin->id,
            'user_id' => $otherUser->id,
        ]);

        $date = now()->format('Y-m-d');
        $commentStatKey = "trending:stats:comments:{$date}:{$otherPlugin->id}";
        $starStatKey = "trending:stats:stars:{$date}:{$otherPlugin->id}";
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $todayKey = "trending:active_plugins:{$date}";

        // Setup Redis trending state
        Redis::zadd($zsetKey, 50, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 10);
        Redis::sadd($todayKey, $plugin->id);

        Redis::set($commentStatKey, 1);
        Redis::set($starStatKey, 1);

        $pluginViewsKey = "trending:stats:views:{$date}:{$plugin->id}";
        $pluginCommentsKey = "trending:stats:comments:{$date}:{$plugin->id}";
        $pluginStarsKey = "trending:stats:stars:{$date}:{$plugin->id}";
        Redis::set($pluginViewsKey, 20);
        Redis::set($pluginCommentsKey, 10);
        Redis::set($pluginStarsKey, 5);

        $otherPlugin->comment_count = 1;
        $otherPlugin->star_count = 1;
        Redis::zadd($zsetKey, 80, $otherPlugin->id);
        Redis::hset($hashKey, $otherPlugin->id, serialize($otherPlugin));

        // Act: soft delete user
        $user->delete();

        // 1. Tokens revoked
        $this->assertCount(0, $user->fresh()->tokens);

        // 2. User's plugins soft-deleted and removed from Redis trending
        $this->assertTrue($plugin->fresh()->trashed());
        $this->assertTrue($commentOnUserPlugin->fresh()->trashed());
        $this->assertTrue($starOnUserPlugin->fresh()->trashed());
        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($todayKey, $plugin->id));
        $this->assertFalse((bool) Redis::exists($pluginViewsKey));
        $this->assertFalse((bool) Redis::exists($pluginCommentsKey));
        $this->assertFalse((bool) Redis::exists($pluginStarsKey));

        // 3. User's comments soft-deleted and trending stats decremented
        $this->assertTrue($comment->fresh()->trashed());
        $this->assertEquals(0, (int) Redis::get($commentStatKey));

        // 4. User's stars soft-deleted and trending stats decremented
        $this->assertTrue($star->fresh()->trashed());
        $this->assertEquals(0, (int) Redis::get($starStatKey));

        // Target plugin's cached star and comment count updated in Redis
        $cachedRaw = Redis::hget($hashKey, $otherPlugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedOtherPlugin = unserialize($cachedRaw);
        $this->assertEquals(0, $cachedOtherPlugin->star_count);
        $this->assertEquals(0, $cachedOtherPlugin->comment_count);
    }

    public function test_restoring_user_cascades_plugins_comments_and_stars(): void
    {
        $user = User::factory()->create();

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

        $date = now()->format('Y-m-d');
        $commentStatKey = "trending:stats:comments:{$date}:{$otherPlugin->id}";
        $starStatKey = "trending:stats:stars:{$date}:{$otherPlugin->id}";
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');

        Redis::set($commentStatKey, 1);
        Redis::set($starStatKey, 1);
        $otherPlugin->comment_count = 1;
        $otherPlugin->star_count = 1;
        Redis::zadd($zsetKey, 80, $otherPlugin->id);
        Redis::hset($hashKey, $otherPlugin->id, serialize($otherPlugin));

        // Soft delete user
        $user->delete();
        $this->assertTrue($plugin->fresh()->trashed());
        $this->assertTrue($comment->fresh()->trashed());
        $this->assertTrue($star->fresh()->trashed());
        $this->assertEquals(0, (int) Redis::get($commentStatKey));
        $this->assertEquals(0, (int) Redis::get($starStatKey));

        // Act: restore user
        $user->restore();

        // 1. User's plugins restored
        $this->assertFalse($plugin->fresh()->trashed());

        // 2. User's comments restored and stats re-incremented
        $this->assertFalse($comment->fresh()->trashed());
        $this->assertEquals(1, (int) Redis::get($commentStatKey));

        // 3. User's stars restored and stats re-incremented
        $this->assertFalse($star->fresh()->trashed());
        $this->assertEquals(1, (int) Redis::get($starStatKey));

        // Target plugin's cached star and comment count restored in Redis
        $cachedRaw = Redis::hget($hashKey, $otherPlugin->id);
        $this->assertNotEmpty($cachedRaw);
        $cachedOtherPlugin = unserialize($cachedRaw);
        $this->assertEquals(1, $cachedOtherPlugin->star_count);
        $this->assertEquals(1, $cachedOtherPlugin->comment_count);
    }

    public function test_locking_user_revokes_tokens_and_removes_plugins_from_trending_cache(): void
    {
        $user = User::factory()->create();
        $user->createToken('test-token');
        $this->assertCount(1, $user->tokens);

        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $date = now()->format('Y-m-d');
        $zsetKey = config('plugins.trending.keys.zset');
        $hashKey = config('plugins.trending.keys.objects');
        $bufferKey = config('plugins.views_buffer_key');
        $todayKey = "trending:active_plugins:{$date}";

        Redis::zadd($zsetKey, 50, $plugin->id);
        Redis::hset($hashKey, $plugin->id, serialize($plugin));
        Redis::hset($bufferKey, $plugin->id, 10);
        Redis::sadd($todayKey, $plugin->id);

        // Act: lock user
        $user->update(['locked_at' => now()]);

        // 1. Tokens revoked
        $this->assertCount(0, $user->fresh()->tokens);

        // 2. Plugins removed from Redis trending caches
        $this->assertFalse((bool) Redis::zscore($zsetKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($hashKey, $plugin->id));
        $this->assertFalse((bool) Redis::hexists($bufferKey, $plugin->id));
        $this->assertFalse((bool) Redis::sismember($todayKey, $plugin->id));
    }

    public function test_unlocking_user_does_not_mark_plugins_active_without_interactions(): void
    {
        $user = User::factory()->create([
            'locked_at' => now(),
        ]);

        $plugin = Plugin::factory()->create([
            'user_id' => $user->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $date = now()->format('Y-m-d');
        $todayKey = "trending:active_plugins:{$date}";
        $this->assertFalse((bool) Redis::sismember($todayKey, $plugin->id));

        // Act: unlock user
        $user->update(['locked_at' => null]);

        // Plugin should NOT be marked active without interaction
        $this->assertFalse((bool) Redis::sismember($todayKey, $plugin->id));
    }

    public function test_locking_user_sends_account_locked_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'locked_user@example.com',
            'locked_at' => null,
        ]);

        // Act: lock user
        $user->update(['locked_at' => now()]);

        Mail::assertSent(AccountLockedMail::class, function (AccountLockedMail $mail) use ($user) {
            return $mail->hasTo('locked_user@example.com')
                && $mail->user->id === $user->id;
        });
    }

    public function test_unlocking_user_does_not_send_account_locked_email(): void
    {
        $user = User::factory()->create([
            'email' => 'user_to_unlock@example.com',
            'locked_at' => now(),
        ]);

        Mail::fake();

        // Act: unlock user
        $user->update(['locked_at' => null]);

        Mail::assertNotSent(AccountLockedMail::class);
    }

    public function test_updating_other_user_attributes_does_not_send_account_locked_email(): void
    {
        $user = User::factory()->create([
            'email' => 'normal_user@example.com',
            'locked_at' => null,
        ]);

        Mail::fake();

        // Act: update name only
        $user->update(['name' => 'Updated Name']);

        Mail::assertNotSent(AccountLockedMail::class);
    }

    public function test_soft_deleting_user_cascades_all_comments_when_more_than_one_chunk(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $now = now();
        $totalComments = 1050;
        $records = [];
        for ($i = 0; $i < $totalComments; $i++) {
            $records[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'plugin_id' => $plugin->id,
                'author_id' => $user->id,
                'content' => "Comment {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert in chunks of 100 for SQLite parameter safety
        foreach (array_chunk($records, 100) as $chunk) {
            Comment::insert($chunk);
        }

        $this->assertEquals($totalComments, Comment::where('author_id', $user->id)->count());

        // Act: soft delete user
        $user->delete();

        // Assert: All 1050 comments must be trashed
        $this->assertEquals(0, Comment::where('author_id', $user->id)->count());
        $this->assertEquals($totalComments, Comment::onlyTrashed()->where('author_id', $user->id)->count());
    }

    public function test_restoring_user_does_not_restore_comments_deleted_before_user_deletion(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        // Comment 1: active comment
        $activeComment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        // Comment 2: deleted previously 1 hour ago
        $previouslyDeletedComment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);
        $previouslyDeletedComment->delete();
        $deletedTime = now()->subHour();
        $previouslyDeletedComment->deleted_at = $deletedTime;
        $previouslyDeletedComment->save();

        $this->assertTrue($previouslyDeletedComment->fresh()->trashed());
        $this->assertFalse($activeComment->fresh()->trashed());

        // Advance time so user's deleted_at is strictly after comment's deleted_at
        $this->travel(10)->seconds();

        // Act 1: Soft delete the user
        $user->delete();
        $this->assertTrue($activeComment->fresh()->trashed());
        $this->assertTrue($previouslyDeletedComment->fresh()->trashed());

        // Act 2: Restore the user
        $user->restore();

        // Assert: Active comment that was cascaded is restored
        $this->assertFalse($activeComment->fresh()->trashed());

        // Assert: Previously deleted comment MUST remain trashed
        $this->assertTrue($previouslyDeletedComment->fresh()->trashed());
    }

    public function test_restoring_user_does_not_restore_plugins_deleted_before_user_deletion(): void
    {
        $user = User::factory()->create();

        $activePlugin = Plugin::factory()->create([
            'user_id' => $user->id,
        ]);

        $previouslyDeletedPlugin = Plugin::factory()->create([
            'user_id' => $user->id,
        ]);
        $previouslyDeletedPlugin->delete();
        $previouslyDeletedPlugin->deleted_at = now()->subHour();
        $previouslyDeletedPlugin->save();

        $this->travel(10)->seconds();

        $user->delete();
        $user->restore();

        $this->assertFalse($activePlugin->fresh()->trashed());
        $this->assertTrue($previouslyDeletedPlugin->fresh()->trashed());
    }

    public function test_soft_deleting_user_dispatches_cascade_delete_job(): void
    {
        Queue::fake([CascadeDeleteUserData::class]);

        $user = User::factory()->create();
        $user->delete();

        Queue::assertPushed(CascadeDeleteUserData::class, function ($job) use ($user) {
            return $job->userId === $user->id;
        });
    }
}
