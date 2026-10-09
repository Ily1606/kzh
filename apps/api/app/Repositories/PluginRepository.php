<?php

namespace App\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

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
        return $this->baseQuery()
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
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Plugin
    {
        /** @var Plugin $updated */
        $updated = parent::update($model, $attributes);

        return $updated;
    }

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->withCount([
                'stars as star_count',
                'comments as comment_count' => fn (Builder $comments) => $comments->visible(),
            ])
            ->orderByDesc('approved_at')
            ->paginate($perPage);
    }


    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', now()->subDays($daysLimit))
            ->selectRaw("*, {$this->starCountSql()} as star_count, {$this->commentCountSql()} as comment_count, (
                (view_count * ? + {$this->commentCountSql()} * ? + {$this->starCountSql()} * ?)
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

    /**
     * Shared skeleton for every query that feeds `PluginResource`.
     *
     * @return Builder<Plugin>
     */
    private function baseQuery(): Builder
    {
        return $this->newQuery()->with(['user.profile', 'categories', 'tags']);
    }

    private function getAgeInSecondsSql(): string
    {
        return match ($this->model->getConnection()->getDriverName()) {
            'sqlite' => "(strftime('%s', 'now') - strftime('%s', approved_at))",
            'pgsql' => 'EXTRACT(EPOCH FROM (NOW() - approved_at))',
            default => '(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(approved_at))',
        };
    }

    private function starCountSql(): string
    {
        $prefix = $this->model->getConnection()->getTablePrefix();

        return "(select count(*) from {$prefix}stars where {$prefix}stars.plugin_id = {$prefix}plugins.id)";
    }

    private function commentCountSql(): string
    {
        $prefix = $this->model->getConnection()->getTablePrefix();

        return "(select count(*) from {$prefix}comments
            where {$prefix}comments.plugin_id = {$prefix}plugins.id
            and {$prefix}comments.hidden_at is null
            and {$prefix}comments.deleted_at is null)";
    }

    public function getTopAllTimePlugins(int $limit): Collection
    {
        return $this->baseQuery()
            ->where('status', PluginStatus::Approved)
            ->whereNotNull('approved_at')
            ->selectRaw("*, {$this->starCountSql()} as star_count, {$this->commentCountSql()} as comment_count")
            ->orderByDesc('view_count')
            ->orderByRaw($this->starCountSql().' desc')
            ->limit($limit)
            ->get();
    }
}
