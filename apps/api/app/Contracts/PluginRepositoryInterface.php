<?php

namespace App\Contracts;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface PluginRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plugin;

    public function findApprovedById(string $id): Plugin;

    /**
     * Find any plugin by ID, whatever its review status, or fail with a 404.
     */
    public function findById(string $id): Plugin;

    /**
     * Write the given attributes onto the plugin and return it re-read.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Plugin;

    public function incrementCommentCount(string $pluginId): void;

    /**
     * @param  array<int, string>  $ids
     * @return Collection<int, Plugin>
     */
    public function findApprovedByIds(array $ids): Collection;

    public function incrementViewCount(string $id, int $count): void;

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator;

    /**
     * @param  array{view: float, comment: float, star: float}  $weights
     * @return Collection<int, Plugin>
     */
    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTopAllTimePlugins(int $limit): Collection;
}
