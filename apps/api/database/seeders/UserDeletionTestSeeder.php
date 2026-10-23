<?php

namespace Database\Seeders;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginLicense;
use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\PluginDailyView;
use App\Models\Star;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\TrendingTracker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class UserDeletionTestSeeder extends Seeder
{
    public const TARGET_USER_EMAIL = 'test-delete-user@example.com';
    public const REVIEWER_USER_EMAIL = 'test-reviewer@example.com';

    public const TARGET_USER_ID = '01920000-0000-7000-8000-000000000001';
    public const REVIEWER_USER_ID = '01920000-0000-7000-8000-000000000002';
    public const PLUGIN_A_ID = '01920000-0000-7000-8000-00000000000a';
    public const PLUGIN_B_ID = '01920000-0000-7000-8000-00000000000b';
    public const PLUGIN_EXT_ID = '01920000-0000-7000-8000-00000000000e';

    public function run(): void
    {
        $this->command->info('Setting up user deletion test scenario...');

        // 1. Clean up existing test data to ensure clean, repeatable state
        $this->cleanupPreviousRun();

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        // 2. Create Target User (the user to be deleted in admin panel)
        $targetUser = User::create([
            'id' => self::TARGET_USER_ID,
            'name' => 'Test Delete User',
            'email' => self::TARGET_USER_EMAIL,
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_admin' => false,
            'locked_at' => null,
        ]);

        UserProfile::create([
            'user_id' => $targetUser->id,
            'avatar_link' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
            'github_name' => 'test-delete-gh',
            'github_link' => 'https://github.com/test-delete-gh',
        ]);

        // Create 2 active API tokens
        $targetUser->createToken('desktop-session');
        $targetUser->createToken('mobile-session');

        // 3. Create Reviewer / Third-party User (owner of third-party plugins & other interactions)
        $reviewer = User::create([
            'id' => self::REVIEWER_USER_ID,
            'name' => 'Test Reviewer',
            'email' => self::REVIEWER_USER_EMAIL,
            'email_verified_at' => now(),
            'password' => Hash::make('Password123!'),
            'is_admin' => false,
        ]);

        $category = Category::firstOrCreate(['name' => 'Developer Tools']);
        $tagTesting = Tag::firstOrCreate(['name' => 'testing']);
        $tagUtilities = Tag::firstOrCreate(['name' => 'utilities']);

        // 4. Create Plugins Owned by Target User
        // 4.1. Plugin A (Approved, trending, active)
        $pluginA = Plugin::create([
            'id' => self::PLUGIN_A_ID,
            'user_id' => $targetUser->id,
            'name' => 'target-user-tool',
            'title' => 'Target User Approved Tool',
            'license' => PluginLicense::MIT->value,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(5),
            'source_link' => 'https://github.com/test-delete-gh/target-user-tool',
            'view_count' => 250,
        ]);
        $pluginA->categories()->sync([$category->id]);
        $pluginA->tags()->sync([$tagTesting->id, $tagUtilities->id]);

        // Daily views in database for Plugin A
        PluginDailyView::create([
            'plugin_id' => $pluginA->id,
            'date' => $today,
            'views_count' => 100,
        ]);
        PluginDailyView::create([
            'plugin_id' => $pluginA->id,
            'date' => $yesterday,
            'views_count' => 150,
        ]);

        // Comments on Plugin A:
        // - Root comment by reviewer
        $commentA1 = Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginA->id,
            'author_id' => $reviewer->id,
            'parent_comment_id' => null,
            'content' => 'Reviewer: Excellent tool created by target user!',
            'created_at' => now()->subDay(),
        ]);

        // - Reply by target user
        Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginA->id,
            'author_id' => $targetUser->id,
            'parent_comment_id' => $commentA1->id,
            'content' => 'Target User: Thank you for your feedback!',
            'created_at' => now(),
        ]);

        // - Root comment by target user
        Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginA->id,
            'author_id' => $targetUser->id,
            'parent_comment_id' => null,
            'content' => 'Target User: Official release v1.0 is now live.',
            'created_at' => now(),
        ]);

        // Star on Plugin A by reviewer
        Star::create([
            'plugin_id' => $pluginA->id,
            'user_id' => $reviewer->id,
            'created_at' => now()->subDay(),
        ]);

        // 4.2. Plugin B (Pending submission owned by target user)
        $pluginB = Plugin::create([
            'id' => self::PLUGIN_B_ID,
            'user_id' => $targetUser->id,
            'name' => 'target-user-draft',
            'title' => 'Target User Draft Plugin (Pending)',
            'license' => PluginLicense::Apache20->value,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
            'source_link' => 'https://github.com/test-delete-gh/target-user-draft',
            'view_count' => 0,
        ]);
        $pluginB->categories()->sync([$category->id]);

        Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginB->id,
            'author_id' => $targetUser->id,
            'parent_comment_id' => null,
            'content' => 'Target User: Initial draft submitted for moderation review.',
            'created_at' => now(),
        ]);

        // 5. Create External Plugin (owned by reviewer) to test Target User interactions
        $pluginExternal = Plugin::create([
            'id' => self::PLUGIN_EXT_ID,
            'user_id' => $reviewer->id,
            'name' => 'external-community-plugin',
            'title' => 'Community Plugin by Another Author',
            'license' => PluginLicense::MIT->value,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(10),
            'source_link' => 'https://github.com/test-reviewer/community-plugin',
            'view_count' => 500,
        ]);
        $pluginExternal->categories()->sync([$category->id]);
        $pluginExternal->tags()->sync([$tagUtilities->id]);

        PluginDailyView::create([
            'plugin_id' => $pluginExternal->id,
            'date' => $today,
            'views_count' => 200,
        ]);
        PluginDailyView::create([
            'plugin_id' => $pluginExternal->id,
            'date' => $yesterday,
            'views_count' => 300,
        ]);

        // 5.1 Comments on External Plugin:
        // - Root comment by Target User
        $commentExtTargetRoot = Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginExternal->id,
            'author_id' => $targetUser->id,
            'parent_comment_id' => null,
            'content' => 'Target User: Great plugin, but found a small bug with options.',
            'created_at' => now(),
        ]);

        // - Reply by Reviewer to Target User's comment (tests child reply cascade deletion!)
        Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginExternal->id,
            'author_id' => $reviewer->id,
            'parent_comment_id' => $commentExtTargetRoot->id,
            'content' => 'Reviewer: Thank you for reporting! Fixing it in the next patch.',
            'created_at' => now(),
        ]);

        // - Independent comment by Reviewer (MUST NOT be deleted!)
        Comment::create([
            'id' => (string) Str::uuid(),
            'plugin_id' => $pluginExternal->id,
            'author_id' => $reviewer->id,
            'parent_comment_id' => null,
            'content' => 'Reviewer: Welcome everyone to community-plugin!',
            'created_at' => now()->subDay(),
        ]);

        // 5.2 Stars on External Plugin:
        // - Star by Target User (MUST be soft deleted!)
        Star::create([
            'plugin_id' => $pluginExternal->id,
            'user_id' => $targetUser->id,
            'created_at' => now(),
        ]);

        // - Star by Reviewer (MUST NOT be deleted!)
        Star::create([
            'plugin_id' => $pluginExternal->id,
            'user_id' => $reviewer->id,
            'created_at' => now()->subDay(),
        ]);

        // 6. Populate Redis Trending & Interaction Caches
        $this->seedRedisData($pluginA, $pluginExternal, $today, $yesterday);

        // 7. Print summary and verification checklist
        $this->printSummary($targetUser, $pluginA, $pluginB, $pluginExternal, $today, $yesterday);
    }

    /**
     * Seed Redis trending sets, objects hash, views buffer, and daily interaction stats.
     */
    private function seedRedisData(
        Plugin $pluginA,
        Plugin $pluginExternal,
        string $today,
        string $yesterday
    ): void {
        try {
            $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
            $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
            $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');

            // 1. Views buffer
            if ($bufferKey) {
                Redis::hset($bufferKey, $pluginA->id, 20);
                Redis::hset($bufferKey, $pluginExternal->id, 15);
            }

            // 2. Trending ZSET
            if ($zsetKey) {
                Redis::zadd($zsetKey, 180, $pluginA->id);
                Redis::zadd($zsetKey, 350, $pluginExternal->id);
            }

            // 3. Trending objects hash
            if ($hashKey) {
                $pluginARepo = app(PluginRepositoryInterface::class)->findApprovedByIds([$pluginA->id])->first() ?? $pluginA;
                $pluginExtRepo = app(PluginRepositoryInterface::class)->findApprovedByIds([$pluginExternal->id])->first() ?? $pluginExternal;
                Redis::hset($hashKey, $pluginA->id, serialize($pluginARepo));
                Redis::hset($hashKey, $pluginExternal->id, serialize($pluginExtRepo));
            }

            // 4. Active plugins per date
            Redis::sadd(TrendingTracker::activeKey($today), $pluginA->id, $pluginExternal->id);
            Redis::sadd(TrendingTracker::activeKey($yesterday), $pluginA->id, $pluginExternal->id);

            // 5. Daily interaction stats for Plugin A (owned by target user)
            Redis::set(TrendingTracker::statKey('views', $today, $pluginA->id), 100);
            Redis::set(TrendingTracker::statKey('views', $yesterday, $pluginA->id), 150);
            Redis::set(TrendingTracker::statKey('comments', $today, $pluginA->id), 2);
            Redis::set(TrendingTracker::statKey('comments', $yesterday, $pluginA->id), 1);
            Redis::set(TrendingTracker::statKey('stars', $yesterday, $pluginA->id), 1);

            // 6. Daily interaction stats for Plugin External
            Redis::set(TrendingTracker::statKey('views', $today, $pluginExternal->id), 200);
            Redis::set(TrendingTracker::statKey('views', $yesterday, $pluginExternal->id), 300);
            Redis::set(TrendingTracker::statKey('comments', $today, $pluginExternal->id), 2); // Target user comment + Reviewer reply
            Redis::set(TrendingTracker::statKey('comments', $yesterday, $pluginExternal->id), 1); // Reviewer independent comment
            Redis::set(TrendingTracker::statKey('stars', $today, $pluginExternal->id), 1); // Target user star
            Redis::set(TrendingTracker::statKey('stars', $yesterday, $pluginExternal->id), 1); // Reviewer star

            // 7. Star timeline cache
            Cache::put("plugins:{$pluginA->id}:star_timeline", ['data' => 'test_cached_timeline'], 900);
            Cache::put("plugins:{$pluginExternal->id}:star_timeline", ['data' => 'test_cached_timeline'], 900);

            $this->command->info('Redis cache keys successfully populated.');
        } catch (\Throwable $e) {
            $this->command->warn('Could not populate Redis: '.$e->getMessage());
        }
    }

    /**
     * Clean up previous test seed run to ensure repeatable test runs.
     */
    private function cleanupPreviousRun(): void
    {
        // 1. Find all target & reviewer users and plugins by ID, email, or name
        $userIds = User::withTrashed()
            ->whereIn('email', [self::TARGET_USER_EMAIL, self::REVIEWER_USER_EMAIL])
            ->orWhereIn('id', [self::TARGET_USER_ID, self::REVIEWER_USER_ID])
            ->pluck('id')
            ->all();

        $pluginIds = Plugin::withTrashed()
            ->whereIn('name', ['target-user-tool', 'target-user-draft', 'external-community-plugin'])
            ->orWhereIn('id', [self::PLUGIN_A_ID, self::PLUGIN_B_ID, self::PLUGIN_EXT_ID])
            ->orWhereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        // 2. Clean up Redis entries for these plugins
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

                for ($i = 0; $i <= $daysLimit; $i++) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    Redis::srem(TrendingTracker::activeKey($date), ...$pluginIds);

                    foreach ($pluginIds as $pid) {
                        Redis::del(TrendingTracker::statKey('views', $date, $pid));
                        Redis::del(TrendingTracker::statKey('comments', $date, $pid));
                        Redis::del(TrendingTracker::statKey('stars', $date, $pid));
                        Cache::forget("plugins:{$pid}:star_timeline");
                    }
                }
            }

            // Clean up any ghost entries from trending_objects, zset, buffer
            $allCachedIds = Redis::hkeys($hashKey);
            if (is_array($allCachedIds) && ! empty($allCachedIds)) {
                $ghostIds = array_diff($allCachedIds, Plugin::pluck('id')->all());
                if (! empty($ghostIds)) {
                    Redis::hdel($hashKey, ...$ghostIds);
                    Redis::zrem($zsetKey, ...$ghostIds);
                    Redis::hdel($bufferKey, ...$ghostIds);
                }
            }
        } catch (\Throwable $e) {
            // Redis error handled gracefully
        }

        // 3. Clean up Database records
        if (! empty($pluginIds)) {
            PluginDailyView::whereIn('plugin_id', $pluginIds)->delete();
            Comment::withTrashed()->whereIn('plugin_id', $pluginIds)->forceDelete();
            Star::withTrashed()->whereIn('plugin_id', $pluginIds)->forceDelete();
            Plugin::withTrashed()->whereIn('id', $pluginIds)->forceDelete();
        }

        if (! empty($userIds)) {
            User::withTrashed()->whereIn('id', $userIds)->each(function ($u) {
                $u->tokens()->delete();
                $u->profile()->delete();
            });
            Comment::withTrashed()->whereIn('author_id', $userIds)->forceDelete();
            Star::withTrashed()->whereIn('user_id', $userIds)->forceDelete();
            User::withTrashed()->whereIn('id', $userIds)->forceDelete();
        }
    }

    /**
     * Print verification checklist to console.
     */
    private function printSummary(
        User $targetUser,
        Plugin $pluginA,
        Plugin $pluginB,
        Plugin $pluginExternal,
        string $today,
        string $yesterday
    ): void {
        $this->command->info('================================================================');
        $this->command->info('  USER DELETION TEST DATA SEEDED SUCCESSFULLY');
        $this->command->info('================================================================');
        $this->command->line("Target User Email    : <fg=yellow>{$targetUser->email}</>");
        $this->command->line('Target User Password : <fg=yellow>Password123!</>');
        $this->command->line("Target User ID       : <fg=cyan>{$targetUser->id}</>");
        $this->command->line('API Tokens Count     : <fg=cyan>2 active tokens</>');
        $this->command->line('User Profile         : <fg=cyan>Bio, Avatar, GitHub link created</>');
        $this->command->line("Owned Plugin A       : <fg=cyan>{$pluginA->name}</> ({$pluginA->id}) [Approved]");
        $this->command->line("Owned Plugin B       : <fg=cyan>{$pluginB->name}</> ({$pluginB->id}) [Pending]");
        $this->command->line("External Plugin      : <fg=cyan>{$pluginExternal->name}</> ({$pluginExternal->id})");
        $this->command->info('----------------------------------------------------------------');
        $this->command->info('VERIFICATION CHECKLIST (AFTER deleting user in /admin/users):');
        $this->command->line('1. [Database] User status changed to deleted (deleted_at is set).');
        $this->command->line('2. [Database] Personal access tokens deleted (count = 0).');
        $this->command->line('3. [Database] Owned Plugin A & B soft-deleted (deleted_at is set).');
        $this->command->line('4. [Database] Comments & stars on Plugin A soft-deleted.');
        $this->command->line('5. [Database] Comments by user on External Plugin soft-deleted.');
        $this->command->line('6. [Database] Child replies to user comments on External Plugin soft-deleted.');
        $this->command->line('7. [Database] Stars by user on External Plugin soft-deleted.');
        $this->command->line('8. [Database] External Plugin itself & reviewer independent comment remain active.');
        $this->command->line('9. [Redis] Plugin A removed from ZSET, Objects hash, and Views buffer.');
        $this->command->line('10. [Redis] Plugin A daily stats (views/comments/stars) deleted.');
        $this->command->line('11. [Redis] External Plugin comment & star stats decremented.');
        $this->command->line('12. [Redis] External Plugin cached object updated with new counts.');
        $this->command->info('================================================================');
    }
}
