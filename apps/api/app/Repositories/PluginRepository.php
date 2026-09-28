<?php

namespace App\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
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
        return $this->model->newQuery()
            ->where('status', PluginStatus::Approved)
            ->findOrFail($id);
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
                $gravity
            ])
            ->orderByDesc('trending_score')
            ->limit($limit)
            ->get();
    }

    private function getAgeInSecondsSql(): string
    {
        return match ($this->model->getConnection()->getDriverName()) {
            'sqlite' => "(strftime('%s', 'now') - strftime('%s', approved_at))",
            'pgsql'  => "EXTRACT(EPOCH FROM (NOW() - approved_at))",
            default  => "(UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(approved_at))",
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
