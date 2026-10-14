<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'plugin_id',
    'author_id',
    'parent_comment_id',
    'content',
    'hidden_at',
])]
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

    public function cascadeDelete()
    {
        $ids = $this->getDescendantIds();
        $deletedCount = 0;

        if (!empty($ids)) {
            $deletedCount += static::whereIn('id', $ids)->delete();
            CommentReport::whereIn('comment_id', $ids)->delete();
        }

        $wasDeleted = $this->trashed();
        $this->delete();

        if (!$wasDeleted) {
            $deletedCount++;
        }

        CommentReport::where('comment_id', $this->id)->delete();

        if ($deletedCount > 0) {
            DB::table('plugins')
                ->where('id', $this->plugin_id)
                ->decrement('comment_count', $deletedCount);
        }
    }

    public function cascadeRestore()
    {
        $deletedAt = $this->deleted_at;
        if (!$deletedAt) {
            return;
        }

        $ids = $this->getDescendantIds();
        $restoredCount = 0;

        if (!empty($ids)) {
            $restoredCount += static::withTrashed()->whereIn('id', $ids)->where('deleted_at', '>=', $deletedAt)->restore();
            CommentReport::withTrashed()->whereIn('comment_id', $ids)->where('deleted_at', '>=', $deletedAt)->restore();
        }

        $this->restore();
        $restoredCount++;

        CommentReport::withTrashed()->where('comment_id', $this->id)->where('deleted_at', '>=', $deletedAt)->restore();

        if ($restoredCount > 0) {
            DB::table('plugins')
                ->where('id', $this->plugin_id)
                ->increment('comment_count', $restoredCount);
        }
    }

    public function cascadeHide()
    {
        $ids = $this->getDescendantIds();
        if (!empty($ids)) {
            static::withTrashed()->whereIn('id', $ids)->whereNull('hidden_at')->update(['hidden_at' => now()]);
            // hidden_at doesn't need to delete reports?
            // Wait, previous code had `CommentReport::whereIn('comment_id', $ids)->delete();`
        }

        $this->update(['hidden_at' => now()]);
    }

    public function cascadeUnhide()
    {
        $hiddenAt = $this->hidden_at;
        if (!$hiddenAt) {
            $this->update(['hidden_at' => null]);
            return;
        }

        $ids = $this->getDescendantIds();
        if (!empty($ids)) {
            static::withTrashed()->whereIn('id', $ids)->where('hidden_at', '>=', $hiddenAt)->update(['hidden_at' => null]);
        }

        $this->update(['hidden_at' => null]);
    }
}
