<?php

namespace App\Contracts;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
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

    public function findApprovedByIds(array $ids): Collection;

    public function incrementViewCount(string $id, int $count): void;

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

    /**
     * Put the plugin in this category, replacing whatever it was in.
     */
    public function replaceCategory(Plugin $plugin, Category $category): void;

    /**
     * Give the plugin exactly these tags, replacing whatever it had.
     *
     * An empty list clears them all.
     *
     * @param  array<int, Tag>  $tags
     */
    public function replaceTags(Plugin $plugin, array $tags): void;

    /**
     * List the published catalogue.
     *
     * @param  string|null  $category  Restrict to one category by name, or null for all of them.
     */
    public function getPaginatedApprovedPlugins(int $perPage, ?string $category = null): LengthAwarePaginator;

    /**
     * List one owner's plugins, whatever their review status.
     *
     * @param  string  $userId  Owner the list is scoped to.
     * @param  PluginStatus|null  $status  Restrict to one status, or null for all of them.
     */
    public function getPaginatedPluginsByUser(string $userId, ?PluginStatus $status, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTopAllTimePlugins(int $limit): Collection;
}
