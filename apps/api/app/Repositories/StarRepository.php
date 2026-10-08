<?php

namespace App\Repositories;

use App\Contracts\StarRepositoryInterface;
use App\Models\Star;

/**
 * @extends BaseRepository<Star>
 */
final class StarRepository extends BaseRepository implements StarRepositoryInterface
{
    public function getModel(): string
    {
        return Star::class;
    }

    public function starredPluginIds(array $pluginIds, string $userId): array
    {
        // No ids means nothing can be starred: return before touching the
        // database so an empty page costs no query at all.
        if ($pluginIds === []) {
            return [];
        }

        return $this->newQuery()
            ->where('user_id', $userId)
            ->whereIn('plugin_id', $pluginIds)
            ->pluck('plugin_id')
            ->all();
    }

    public function insertIgnore(string $pluginId, string $userId): bool
    {

        return $this->newQuery()
            ->insertOrIgnore([
                'plugin_id' => $pluginId,
                'user_id' => $userId,
            ]) === 1;
    }

    public function deleteBy(string $pluginId, string $userId): bool
    {
        return $this->newQuery()
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->delete() === 1;
    }
}
