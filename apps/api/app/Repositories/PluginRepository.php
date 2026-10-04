<?php

namespace App\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<Plugin>
 */
class PluginRepository extends BaseRepository implements PluginRepositoryInterface
{
    public function getModel(): string
    {
        return Plugin::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plugin
    {
        /** @var Plugin $plugin */
        $plugin = parent::create($attributes);

        return $plugin;
    }

    public function findApprovedById(string $id): Plugin
    {
        return $this->model->newQuery()
            ->where('status', PluginStatus::Approved)
            ->findOrFail($id);
    }

    public function findById(string $id): Plugin
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * Write the given attributes onto the plugin and return it re-read.
     *
     * `updated_at` moving is correct here, unlike in incrementCommentCount() or
     * changeStarCount(): those are bookkeeping writes, while this is a real
     * edit of the plugin's content.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Plugin
    {
        /** @var Plugin $updated */
        $updated = parent::update($model, $attributes);

        return $updated;
    }

    /**
     * `DB::table()` returns the plain query builder, not the Eloquent one, and
     * that is the point: Eloquent's `Builder::increment()` calls
     * `addUpdatedAtColumn()`, which would rewrite `updated_at` on every comment.
     * A new comment is not an edit of the plugin, so the timestamp stays put.
     * `DB::table()` still shares the connection with the caller's transaction,
     * so the bump rolls back with the insert like any other write.
     *
     * DB::table() will update `comment_count` but will not update the Plugin's `updated_at` field.
     */
    public function incrementCommentCount(string $pluginId): void
    {
        DB::table('plugins')->where('id', $pluginId)->increment('comment_count');
    }

    /**
     * Move `star_count` by a signed amount: positive adds, negative subtracts,
     * zero returns without touching the database.
     *
     * Three branches because the floor-at-zero rule only makes sense in one of
     * them. Decrementing adds `where star_count >= abs($amount)` so the statement
     * matches nothing once the counter would go negative — `unsignedInteger` is
     * enforced by MySQL alone, and on PostgreSQL an unguarded decrement would
     * happily store -1. That guard is what makes two concurrent unstars safe:
     * the loser of the race matches zero rows and its decrement does nothing.
     *
     * Incrementing needs no such guard, since it cannot exceed a ceiling.
     *
     * `DB::table()` throughout, for the same reason as incrementCommentCount():
     * Eloquent's `increment()` adds `updated_at` to the statement, and starring
     * is not an edit of the plugin.
     */
    public function changeStarCount(string $pluginId, int $amount): void
    {
        if ($amount === 0) {
            return;
        }

        if ($amount > 0) {
            DB::table('plugins')->where('id', $pluginId)->increment('star_count', $amount);

            return;
        }

        $decrement = abs($amount);

        DB::table('plugins')
            ->where('id', $pluginId)
            ->where('star_count', '>=', $decrement)
            ->decrement('star_count', $decrement);
    }

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->orderByDesc('approved_at')
            ->paginate($perPage);
    }

    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection
    {
        return $this->model->newQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subDays($daysLimit))
            ->selectRaw("*, (
                (view_count * ? + comment_count * ? + star_count * ?)
                / POWER({$this->getAgeInSecondsSql()}/3600.0 + ?, ?)
            ) as trending_score", [
                $weights['view'],
                $weights['comment'],
                $weights['star'],
                $ageOffset,
                $gravity,
            ])
            ->orderByDesc('trending_score')
            ->limit($limit)
            ->get();
    }

    private function getAgeInSecondsSql(): string
    {
        return match ($this->model->getConnection()->getDriverName()) {
            'sqlite' => "(strftime('%s', 'now') - strftime('%s', approved_at))",
            'pgsql' => 'EXTRACT(EPOCH FROM (NOW() - approved_at))',
            default => '(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(approved_at))',
        };
    }

    public function getTopAllTimePlugins(int $limit): Collection
    {
        return $this->model->newQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->orderByDesc('view_count')
            ->orderByDesc('star_count')
            ->limit($limit)
            ->get();
    }
}
