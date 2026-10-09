<?php

namespace App\Contracts;

interface StarRepositoryInterface
{
    /**
     * The subset of `$pluginIds` that this user has starred.
     *
     * @param  list<string>  $pluginIds
     * @return list<string>
     */
    public function starredPluginIds(array $pluginIds, string $userId): array;

    /**
     * Insert the star, reporting whether a row was actually created.
     */
    public function insertIgnore(string $pluginId, string $userId): bool;

    /**
     * Remove the star, reporting whether a row was actually deleted.
     */
    public function deleteBy(string $pluginId, string $userId): bool;

    /**
     * Get star count grouped by date for a plugin.
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getTimeline(string $pluginId, bool $byMonth = false);
}
