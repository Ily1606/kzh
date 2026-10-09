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

    public function getTimeline(string $pluginId, bool $byMonth = false)
    {
        $query = $this->newQuery()->where('plugin_id', $pluginId);

        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $dateFormat = $byMonth ? "TO_CHAR(created_at, 'YYYY-MM')" : "TO_CHAR(created_at, 'YYYY-MM-DD')";
        } elseif ($driver === 'sqlite') {
            $dateFormat = $byMonth ? "strftime('%Y-%m', created_at)" : "DATE(created_at)";
        } else {
            $dateFormat = $byMonth ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE(created_at)";
        }

        return $query->selectRaw("{$dateFormat} as date, count(*) as count")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    }
}
