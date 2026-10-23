<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Plugin;
use App\Models\Star;
use App\Models\User;
use App\Services\TrendingTracker;
use Database\Seeders\UserDeletionTestSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('test:check-user-deletion')]
#[Description('Inspect Database and Redis state for the user deletion test scenario')]
class CheckUserDeletionData extends Command
{
    public function handle(): int
    {
        $targetEmail = UserDeletionTestSeeder::TARGET_USER_EMAIL;
        $user = User::withTrashed()->where('email', $targetEmail)->first();

        if (! $user) {
            $this->error("Target user [{$targetEmail}] does not exist. Run seeder first:");
            $this->line('  php artisan db:seed --class=UserDeletionTestSeeder');

            return self::FAILURE;
        }

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        $pluginA = Plugin::withTrashed()->where('name', 'target-user-tool')->first();
        $pluginB = Plugin::withTrashed()->where('name', 'target-user-draft')->first();
        $pluginExt = Plugin::withTrashed()->where('name', 'external-community-plugin')->first();

        $zsetKey = config('plugins.trending.keys.zset', 'plugins:trending_zset');
        $hashKey = config('plugins.trending.keys.objects', 'plugins:trending_objects');
        $bufferKey = config('plugins.views_buffer_key', 'plugins:views_buffer');

        $this->info('================================================================');
        $this->info('           USER DELETION STATUS INSPECTOR                       ');
        $this->info('================================================================');

        // 1. User & Tokens
        $this->line('<fg=yellow;options=bold>1. TARGET USER & TOKENS</>');
        $this->table(
            ['Check Item', 'Value', 'Status'],
            [
                ['User ID', $user->id, ''],
                ['Email', $user->email, ''],
                ['Status Badge', $user->status(), $user->trashed() ? '<fg=green>DELETED (OK)</>' : '<fg=yellow>ACTIVE (NOT DELETED YET)</>'],
                ['Deleted At', (string) $user->deleted_at, $user->trashed() ? '<fg=green>SET (OK)</>' : '<fg=yellow>NULL</>'],
                ['Active Tokens Count', (string) $user->tokens()->count(), $user->tokens()->count() === 0 ? '<fg=green>0 (CLEANED UP)</>' : "<fg=yellow>{$user->tokens()->count()} (ACTIVE)</>"],
            ]
        );

        // 2. Owned Plugins
        if ($pluginA) {
            $inZset = Redis::zscore($zsetKey, $pluginA->id) !== null && Redis::zscore($zsetKey, $pluginA->id) !== false;
            $inObjects = (bool) Redis::hexists($hashKey, $pluginA->id);
            $inBuffer = (bool) Redis::hexists($bufferKey, $pluginA->id);
            $inActiveSet = (bool) Redis::sismember(TrendingTracker::activeKey($today), $pluginA->id);

            $viewsStat = Redis::get(TrendingTracker::statKey('views', $today, $pluginA->id));
            $commentsStat = Redis::get(TrendingTracker::statKey('comments', $today, $pluginA->id));
            $starsStat = Redis::get(TrendingTracker::statKey('stars', $yesterday, $pluginA->id));

            $this->newLine();
            $this->line('<fg=yellow;options=bold>2. OWNED PLUGIN A (target-user-tool)</>');
            $this->table(
                ['Storage', 'Key / Item', 'Current Value', 'Status'],
                [
                    ['Database', 'Plugin Soft-Deleted', $pluginA->trashed() ? 'Yes' : 'No', $pluginA->trashed() ? '<fg=green>DELETED (OK)</>' : '<fg=yellow>LIVE</>'],
                    ['Database', 'Active Comments Count', (string) Comment::where('plugin_id', $pluginA->id)->count(), Comment::where('plugin_id', $pluginA->id)->count() === 0 ? '<fg=green>0 (DELETED)</>' : '<fg=yellow>EXISTS</>'],
                    ['Database', 'Active Stars Count', (string) Star::where('plugin_id', $pluginA->id)->count(), Star::where('plugin_id', $pluginA->id)->count() === 0 ? '<fg=green>0 (DELETED)</>' : '<fg=yellow>EXISTS</>'],
                    ['Redis', "ZSET ({$zsetKey})", $inZset ? 'Present' : 'Not Found', $inZset ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                    ['Redis', "Objects Hash ({$hashKey})", $inObjects ? 'Present' : 'Not Found', $inObjects ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                    ['Redis', "Views Buffer ({$bufferKey})", $inBuffer ? 'Present' : 'Not Found', $inBuffer ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                    ['Redis', "Active Set ({$today})", $inActiveSet ? 'Present' : 'Not Found', $inActiveSet ? '<fg=yellow>EXISTS</>' : '<fg=green>REMOVED (OK)</>'],
                    ['Redis', 'Daily Views Stat (Today)', $viewsStat ?? 'null', $viewsStat !== null ? "<fg=yellow>{$viewsStat}</>" : '<fg=green>DELETED (OK)</>'],
                    ['Redis', 'Daily Comments Stat (Today)', $commentsStat ?? 'null', $commentsStat !== null ? "<fg=yellow>{$commentsStat}</>" : '<fg=green>DELETED (OK)</>'],
                    ['Redis', 'Daily Stars Stat (Yesterday)', $starsStat ?? 'null', $starsStat !== null ? "<fg=yellow>{$starsStat}</>" : '<fg=green>DELETED (OK)</>'],
                ]
            );
        }

        // 3. External Plugin Interactions
        if ($pluginExt) {
            $userComment = Comment::withTrashed()->where('plugin_id', $pluginExt->id)->where('author_id', $user->id)->first();
            $userStar = Star::withTrashed()->where('plugin_id', $pluginExt->id)->where('user_id', $user->id)->first();

            $extCommentsToday = Redis::get(TrendingTracker::statKey('comments', $today, $pluginExt->id));
            $extStarsToday = Redis::get(TrendingTracker::statKey('stars', $today, $pluginExt->id));

            $cachedRaw = Redis::hget($hashKey, $pluginExt->id);
            $cachedCommentCount = '-';
            $cachedStarCount = '-';
            if ($cachedRaw) {
                $cachedObj = @unserialize($cachedRaw);
                $cachedCommentCount = (string) ($cachedObj->comment_count ?? '-');
                $cachedStarCount = (string) ($cachedObj->star_count ?? '-');
            }

            $this->newLine();
            $this->line('<fg=yellow;options=bold>3. EXTERNAL PLUGIN INTERACTIONS (external-community-plugin)</>');
            $this->table(
                ['Storage', 'Interaction', 'Current Value', 'Status'],
                [
                    ['Database', 'Plugin itself', $pluginExt->trashed() ? 'Trashed' : 'Active', $pluginExt->trashed() ? '<fg=red>ERROR: SHOULD STAY ACTIVE</>' : '<fg=green>ACTIVE (OK)</>'],
                    ['Database', 'User Root Comment', $userComment?->trashed() ? 'Trashed' : 'Active', $userComment?->trashed() ? '<fg=green>DELETED (OK)</>' : '<fg=yellow>LIVE</>'],
                    ['Database', 'User Star', $userStar?->trashed() ? 'Trashed' : 'Active', $userStar?->trashed() ? '<fg=green>DELETED (OK)</>' : '<fg=yellow>LIVE</>'],
                    ['Redis', 'Daily Comments Stat (Today)', $extCommentsToday ?? 'null', (int) $extCommentsToday === 0 ? '<fg=green>0 (DECREMENTED OK)</>' : "<fg=yellow>{$extCommentsToday} (INITIAL)</>"],
                    ['Redis', 'Daily Stars Stat (Today)', $extStarsToday ?? 'null', (int) $extStarsToday === 0 ? '<fg=green>0 (DECREMENTED OK)</>' : "<fg=yellow>{$extStarsToday} (INITIAL)</>"],
                    ['Redis', 'Cached Obj comment_count', $cachedCommentCount, $cachedCommentCount === '1' ? '<fg=green>1 (REFRESHED OK)</>' : "<fg=yellow>{$cachedCommentCount}</>"],
                    ['Redis', 'Cached Obj star_count', $cachedStarCount, $cachedStarCount === '1' ? '<fg=green>1 (REFRESHED OK)</>' : "<fg=yellow>{$cachedStarCount}</>"],
                ]
            );
        }

        $this->newLine();
        $this->info('================================================================');

        return self::SUCCESS;
    }
}
