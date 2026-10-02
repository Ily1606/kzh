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

    /**
     * Move the plugin's star counter by a signed amount: positive adds, negative
     * subtracts, zero is a no-op.
     *
     * Starring and unstarring are the same state change in opposite
     * directions, so one signed method covers both. Splitting it into an
     * increment and a decrement would duplicate the floor-at-zero guard across
     * two methods while only one of them is ever exercised per call site.
     *
     * `unsignedInteger` is only enforced by MySQL; PostgreSQL stores a plain
     * integer, so the guard on the decrement branch — not the column — is what
     * stops concurrent unstars from driving the counter negative.
     */
    public function changeStarCount(string $pluginId, int $amount): void;

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
