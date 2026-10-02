<?php

namespace App\Contracts;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PluginRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plugin;

    public function findApprovedById(string $id): Plugin;

    public function incrementCommentCount(string $pluginId): void;

    public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTopAllTimePlugins(int $limit): Collection;
}
