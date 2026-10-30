<?php

namespace App\Models;

use App\Observers\CommentObserver;
use App\Services\TrendingTracker;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Fillable([
    'plugin_id',
    'author_id',
    'parent_comment_id',
    'content',
    'hidden_at',
])]
#[ObservedBy([CommentObserver::class])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::restoring(function (Comment $comment): bool {
            return false;
        });
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parentComment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_comment_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommentReport::class);
    }

    public function getDescendantIds(): array
    {
        $ids = [];
        $children = static::withTrashed()->where('parent_comment_id', $this->id)->get(['id']);
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        return array_unique($ids);
    }

    public function cascadeDelete(): void
    {
        if ($this->trashed()) {
            return;
        }

        DB::transaction(function (): void {
            $ids = $this->getDescendantIds();
            $allIds = array_merge([$this->id], $ids);

            // 1. Gather visible comment counts grouped by date before soft-deleting
            $visibleStats = static::whereIn('id', $allIds)
                ->whereNull('deleted_at')
                ->whereNull('hidden_at')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            if (!empty($ids)) {
                static::whereIn('id', $ids)->delete();
                CommentReport::whereIn('comment_id', $ids)->delete();
            }

            $this->deleteQuietly();

            CommentReport::where('comment_id', $this->id)->delete();

            // 2. Decrement trending in Redis in batch post-commit
            $pluginId = $this->plugin_id;
            DB::afterCommit(function () use ($visibleStats, $pluginId): void {
                try {
                    foreach ($visibleStats as $date => $count) {
                        TrendingTracker::trackComment($pluginId, -(int) $count, Carbon::parse($date));
                    }
                    app(CommentObserver::class)->refreshCachedPluginCommentCount($pluginId);
                } catch (Throwable $e) {
                    Log::warning("Failed to bulk decrement trending stats in Redis for plugin {$pluginId}: " . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            });
        });
    }

    public function cascadeHide(): void
    {
        if ($this->hidden_at !== null || $this->trashed()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $ids = $this->getDescendantIds();
            $allIds = array_merge([$this->id], $ids);

            // 1. Gather visible comments (not already hidden, not deleted) grouped by date
            $visibleStats = static::whereIn('id', $allIds)
                ->whereNull('deleted_at')
                ->whereNull('hidden_at')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            if (!empty($ids)) {
                static::withTrashed()->whereIn('id', $ids)->whereNull('hidden_at')->update(['hidden_at' => $now]);
            }

            $this->updateQuietly(['hidden_at' => $now]);

            // 2. Decrement trending in Redis in batch post-commit
            $pluginId = $this->plugin_id;
            DB::afterCommit(function () use ($visibleStats, $pluginId): void {
                try {
                    foreach ($visibleStats as $date => $count) {
                        TrendingTracker::trackComment($pluginId, -(int) $count, Carbon::parse($date));
                    }
                    app(CommentObserver::class)->refreshCachedPluginCommentCount($pluginId);
                } catch (Throwable $e) {
                    Log::warning("Failed to bulk decrement trending stats on hide in Redis for plugin {$pluginId}: " . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            });
        });
    }

    public function cascadeUnhide(): void
    {
        if ($this->hidden_at === null || $this->trashed()) {
            return;
        }

        DB::transaction(function (): void {
            $hiddenAt = $this->getRawOriginal('hidden_at') ?? $this->hidden_at;
            $ids = $this->getDescendantIds();

            // Find which comments will be unhidden (only non-deleted ones that were hidden with this cascade)
            $hiddenIdsToUnhide = [];
            if (!empty($ids)) {
                $hiddenIdsToUnhide = static::whereIn('id', $ids)
                    ->whereNull('deleted_at')
                    ->where('hidden_at', $hiddenAt)
                    ->pluck('id')
                    ->all();
            }

            $allUnhidingIds = array_merge([$this->id], $hiddenIdsToUnhide);

            // Gather counts grouped by date to re-increment in Redis
            $unhideStats = static::whereIn('id', $allUnhidingIds)
                ->whereNull('deleted_at')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            if (!empty($hiddenIdsToUnhide)) {
                static::whereIn('id', $hiddenIdsToUnhide)->update(['hidden_at' => null]);
            }

            $this->updateQuietly(['hidden_at' => null]);

            // Re-increment trending in Redis in batch post-commit
            $pluginId = $this->plugin_id;
            DB::afterCommit(function () use ($unhideStats, $pluginId): void {
                try {
                    foreach ($unhideStats as $date => $count) {
                        TrendingTracker::trackComment($pluginId, (int) $count, Carbon::parse($date));
                    }
                    app(CommentObserver::class)->refreshCachedPluginCommentCount($pluginId);
                } catch (Throwable $e) {
                    Log::warning("Failed to bulk increment trending stats on unhide in Redis for plugin {$pluginId}: " . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            });
        });
    }
}
