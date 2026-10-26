<?php

namespace Database\Seeders;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginLicense;
use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\PluginDailyView;
use App\Models\Star;
use App\Models\Tag;
use App\Models\User;
use App\Services\TrendingTracker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class PluginDeletionTestSeeder extends Seeder
{
    public const TARGET_AUTHOR_EMAIL = 'plugin-author@example.com';
    public const COMMUNITY_USER_EMAIL = 'community-user@example.com';

    public const TARGET_AUTHOR_ID = '01930000-0000-7000-8000-000000000001';
    public const COMMUNITY_USER_ID = '01930000-0000-7000-8000-000000000002';

    public const TARGET_PLUGIN_ID = '01930000-0000-7000-8000-00000000000a';
    public const TARGET_PLUGIN_DRAFT_ID = '01930000-0000-7000-8000-00000000000b';
    public const OTHER_PLUGIN_ID = '01930000-0000-7000-8000-00000000000e';

    public function run(): void
    {
        $this->command->info('Setting up plugin deletion test scenario...');

        // 1. Clean up existing test data to ensure clean, repeatable state
        $this->cleanupPreviousRun();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // 2. Create Users
        $author = User::create([
            'id' => self::TARGET_AUTHOR_ID,
            'name' => 'Plugin Author',
            'email' => self::TARGET_AUTHOR_EMAIL,
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_admin' => false,
        ]);

        $communityUser = User::create([
            'id' => self::COMMUNITY_USER_ID,
            'name' => 'Community Member',
            'email' => self::COMMUNITY_USER_EMAIL,
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_admin' => false,
        ]);

        $category = Category::firstOrCreate(['name' => 'Developer Tools']);
        $tagTesting = Tag::firstOrCreate(['name' => 'testing']);
        $tagUtilities = Tag::firstOrCreate(['name' => 'utilities']);

        // 3. Create Target Plugin (Approved, active, trending)
        $targetPlugin = Plugin::create([
            'id' => self::TARGET_PLUGIN_ID,
            'user_id' => $author->id,
            'name' => 'target-plugin-to-delete',
            'title' => 'Target Plugin To Delete',
            'license' => PluginLicense::MIT->value,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(5),
            'source_link' => 'https://github.com/author/target-plugin',
            'view_count' => 300,
        ]);
        $targetPlugin->categories()->sync([$category->id]);
        $targetPlugin->tags()->sync([$tagTesting->id, $tagUtilities->id]);

        // Daily views in database for target plugin
        PluginDailyView::create([
            'plugin_id' => $targetPlugin->id,
            'date' => $today,
            'views_count' => 120,
        ]);
        PluginDailyView::create([
            'plugin_id' => $targetPlugin->id,
            'date' => $yesterday,
            'views_count' => 180,
        ]);

        // 4. Create Draft Plugin owned by same author
        $draftPlugin = Plugin::create([
            'id' => self::TARGET_PLUGIN_DRAFT_ID,
            'user_id' => $author->id,
            'name' => 'target-plugin-draft',
            'title' => 'Author Draft Plugin',
            'license' => PluginLicense::Apache20->value,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
            'source_link' => 'https://github.com/author/draft-plugin',
            'view_count' => 0,
        ]);
        $draftPlugin->categories()->sync([$category->id]);

        // 5. Create Other Plugin (independent plugin owned by community member)
        $otherPlugin = Plugin::create([
            'id' => self::OTHER_PLUGIN_ID,
            'user_id' => $communityUser->id,
            'name' => 'other-community-plugin',
            'title' => 'Other Community Plugin',
            'license' => PluginLicense::MIT->value,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(10),
            'source_link' => 'https://github.com/community/other-plugin',
            'view_count' => 500,
        ]);
        $otherPlugin->categories()->sync([$category->id]);
        $otherPlugin->tags()->sync([$tagTesting->id]);

        PluginDailyView::create([
            'plugin_id' => $otherPlugin->id,
            'date' => $today,
            'views_count' => 200,
        ]);
        PluginDailyView::create([
            'plugin_id' => $otherPlugin->id,
            'date' => $yesterday,
            'views_count' => 300,
        ]);

        // 6. Create Comments on Target Plugin (including nested replies)
        $rootComment = Comment::create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $communityUser->id,
            'parent_comment_id' => null,
            'content' => 'Great plugin! Does it support async processing?',
            'created_at' => now()->subHours(5),
        ]);

        $nestedReply = Comment::create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $author->id,
            'parent_comment_id' => $rootComment->id,
            'content' => 'Yes, async processing is supported in v2.',
            'created_at' => now()->subHours(4),
        ]);

        Comment::create([
            'plugin_id' => $targetPlugin->id,
            'author_id' => $communityUser->id,
            'parent_comment_id' => $nestedReply->id,
            'content' => 'Awesome, thanks for confirming!',
            'created_at' => now()->subHours(3),
        ]);

        // Comment Report on target plugin root comment
        CommentReport::create([
            'plugin_id' => $targetPlugin->id,
            'comment_id' => $rootComment->id,
            'user_id' => $author->id,
            'reason' => 'Test report on comment',
        ]);

        // Stars on target plugin
        Star::create([
            'plugin_id' => $targetPlugin->id,
            'user_id' => $communityUser->id,
            'created_at' => now()->subHours(6),
        ]);

        // 7. Create Comments and Stars on Other Plugin (should remain untouched)
        Comment::create([
            'plugin_id' => $otherPlugin->id,
            'author_id' => $author->id,
            'parent_comment_id' => null,
            'content' => 'Keep up the good work on this other tool!',
            'created_at' => now()->subDays(2),
        ]);

        Star::create([
            'plugin_id' => $otherPlugin->id,
            'user_id' => $author->id,
            'created_at' => now()->subDays(2),
        ]);

        // 8. Seed Redis Trending and Cache data
        $this->seedRedisData($targetPlugin, $otherPlugin, $today, $yesterday);

        $this->command->info('Plugin deletion test scenario seeded successfully!');
    }

    private function seedRedisData(
        Plugin $targetPlugin,
        Plugin $otherPlugin,
        string $today,
        string $yesterday
    ): void {
        try {
            $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
            $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
            $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');
            $processingKey = $bufferKey.'_processing';

            // 1. Views buffer and processing buffer
            if ($bufferKey) {
                Redis::hset($bufferKey, $targetPlugin->id, 25);
                Redis::hset($bufferKey, $otherPlugin->id, 15);
                Redis::hset($processingKey, $targetPlugin->id, 10);
                Redis::hset($processingKey, $otherPlugin->id, 5);
            }

            // 2. Trending ZSET
            if ($zsetKey) {
                Redis::zadd($zsetKey, 200, $targetPlugin->id);
                Redis::zadd($zsetKey, 350, $otherPlugin->id);
            }

            // 3. Trending objects hash
            if ($hashKey) {
                $targetPluginRepo = app(PluginRepositoryInterface::class)->findApprovedByIds([$targetPlugin->id])->first() ?? $targetPlugin;
                $otherPluginRepo = app(PluginRepositoryInterface::class)->findApprovedByIds([$otherPlugin->id])->first() ?? $otherPlugin;
                Redis::hset($hashKey, $targetPlugin->id, serialize($targetPluginRepo));
                Redis::hset($hashKey, $otherPlugin->id, serialize($otherPluginRepo));
            }

            // 4. Active plugins per date
            Redis::sadd(TrendingTracker::activeKey($today), $targetPlugin->id, $otherPlugin->id);
            Redis::sadd(TrendingTracker::activeKey($yesterday), $targetPlugin->id, $otherPlugin->id);

            // 5. Daily interaction stats for Target Plugin
            Redis::set(TrendingTracker::statKey('views', $today, $targetPlugin->id), 120);
            Redis::set(TrendingTracker::statKey('views', $yesterday, $targetPlugin->id), 180);
            Redis::set(TrendingTracker::statKey('comments', $today, $targetPlugin->id), 3);
            Redis::set(TrendingTracker::statKey('stars', $today, $targetPlugin->id), 1);

            // 6. Daily interaction stats for Other Plugin
            Redis::set(TrendingTracker::statKey('views', $today, $otherPlugin->id), 200);
            Redis::set(TrendingTracker::statKey('views', $yesterday, $otherPlugin->id), 300);
            Redis::set(TrendingTracker::statKey('comments', $yesterday, $otherPlugin->id), 1);
            Redis::set(TrendingTracker::statKey('stars', $yesterday, $otherPlugin->id), 1);

            // 7. Star timeline cache
            Cache::put("plugin:star_timeline:{$targetPlugin->id}", ['data' => 'target_star_timeline'], 900);
            Cache::put("plugins:{$targetPlugin->id}:star_timeline", ['data' => 'target_star_timeline'], 900);
            Cache::put("plugin:star_timeline:{$otherPlugin->id}", ['data' => 'other_star_timeline'], 900);
            Cache::put("plugins:{$otherPlugin->id}:star_timeline", ['data' => 'other_star_timeline'], 900);

            $this->command->info('Redis cache keys successfully populated for plugin deletion test.');
        } catch (\Throwable $e) {
            $this->command->warn('Could not populate Redis: '.$e->getMessage());
        }
    }

    private function cleanupPreviousRun(): void
    {
        $userIds = User::withTrashed()
            ->whereIn('email', [self::TARGET_AUTHOR_EMAIL, self::COMMUNITY_USER_EMAIL])
            ->orWhereIn('id', [self::TARGET_AUTHOR_ID, self::COMMUNITY_USER_ID])
            ->pluck('id')
            ->all();

        $pluginIds = Plugin::withTrashed()
            ->whereIn('name', ['target-plugin-to-delete', 'target-plugin-draft', 'other-community-plugin'])
            ->orWhereIn('id', [self::TARGET_PLUGIN_ID, self::TARGET_PLUGIN_DRAFT_ID, self::OTHER_PLUGIN_ID])
            ->orWhereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        // Clean up Redis entries
        try {
            $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
            $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
            $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');
            $processingKey = $bufferKey.'_processing';
            $daysLimit = (int) config('plugins.trending.days_limit', 30);

            if (! empty($pluginIds)) {
                if ($zsetKey) {
                    Redis::zrem($zsetKey, ...$pluginIds);
                }
                if ($hashKey) {
                    Redis::hdel($hashKey, ...$pluginIds);
                }
                if ($bufferKey) {
                    Redis::hdel($bufferKey, ...$pluginIds);
                }
                if ($processingKey) {
                    Redis::hdel($processingKey, ...$pluginIds);
                }

                for ($i = 0; $i < $daysLimit; $i++) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    Redis::srem(TrendingTracker::activeKey($date), ...$pluginIds);
                    foreach ($pluginIds as $pId) {
                        Redis::del(TrendingTracker::statKey('views', $date, $pId));
                        Redis::del(TrendingTracker::statKey('comments', $date, $pId));
                        Redis::del(TrendingTracker::statKey('stars', $date, $pId));
                        Cache::forget("plugin:star_timeline:{$pId}");
                        Cache::forget("plugins:{$pId}:star_timeline");
                    }
                }
            }
        } catch (\Throwable) {
            // Redis might be offline during certain environments
        }

        // Clean up database tables in order
        if (! empty($pluginIds)) {
            CommentReport::whereIn('plugin_id', $pluginIds)->forceDelete();
            Comment::whereIn('plugin_id', $pluginIds)->forceDelete();
            Star::whereIn('plugin_id', $pluginIds)->forceDelete();
            PluginDailyView::whereIn('plugin_id', $pluginIds)->delete();

            $plugins = Plugin::withTrashed()->whereIn('id', $pluginIds)->get();
            foreach ($plugins as $p) {
                $p->categories()->detach();
                $p->tags()->detach();
                $p->forceDelete();
            }
        }

        if (! empty($userIds)) {
            User::withTrashed()->whereIn('id', $userIds)->forceDelete();
        }
    }
}
