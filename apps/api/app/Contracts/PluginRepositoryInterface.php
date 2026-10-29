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
     * Find a plugin by ID without eager-loaded relations.
     */
    public function find(mixed $id): ?Model;

    /**
     * Find any plugin by ID, whatever its review status, or fail with a 404.
     */
    public function findById(string $id): Plugin;

    /**
     * Find a plugin by its author's username and its name
     */
    public function findByUsernameAndPluginName(string $username, string $name): Plugin;

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
     * @param  string|null  $search  Match against name and title, or null to skip the filter.
     * @param  string|null  $userId  Restrict to plugins by this user, or null for all users.
     */
    public function getPaginatedApprovedPlugins(int $perPage, ?string $category = null, ?string $search = null, ?string $userId = null): LengthAwarePaginator;

    /**
     * List one owner's plugins, whatever their review status.
     *
     * @param  string  $userId  Owner the list is scoped to.
     * @param  PluginStatus|null  $status  Restrict to one status, or null for all of them.
     */
    public function getPaginatedPluginsByUser(string $userId, ?PluginStatus $status, int $perPage): LengthAwarePaginator;

    /**
     * Delete the given plugin.
     */
    public function delete(Plugin $plugin): bool;

    /**
     * @return Collection<int, Plugin>
     */
    public function getTopAllTimePlugins(int $limit): Collection;
}
