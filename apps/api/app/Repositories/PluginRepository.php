<?php

namespace App\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
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
            ->approved()
            ->findOrFail($id);
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

    public function incrementViewCount(string $id, int $count): void
    {
        $this->model->newQuery()->where('id', $id)->increment('view_count', $count);
    }

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->approved()
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{view: float, comment: float, star: float}  $weights
     * @return Collection<int, Plugin>
     */
    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit,): Collection
    {
        $secondsInHour = 3600.0;

        return $this->model->newQuery()
            ->approved()
            ->where('approved_at', '>=', now()->subDays($daysLimit))
            ->selectRaw("*, (
                (view_count * {$weights['view']} + comment_count * {$weights['comment']} + star_count * {$weights['star']} - 1)
                / POWER({$this->getAgeInSecondsSql()}/{$secondsInHour} + {$ageOffset}, {$gravity})
            ) as trending_score")
            ->orderByDesc('trending_score')
            ->orderByDesc('id')
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
            ->approved()
            ->orderByDesc('view_count')
            ->orderByDesc('star_count')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
