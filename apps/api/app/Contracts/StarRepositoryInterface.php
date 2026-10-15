<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

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
     * @return Collection
     */
    public function getTimeline(string $pluginId, bool $byMonth = false);

    /**
     * Get daily star counts grouped by plugin and date for the last N days.
     *
     * @return Collection<int, object{plugin_id: string, date: string, count: int}>
     */
    public function getDailyStarCounts(int $daysLimit): Collection;
}
