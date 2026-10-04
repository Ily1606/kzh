<?php

namespace App\Repositories;

use App\Contracts\StarRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class StarRepository implements StarRepositoryInterface
{
    public function starredPluginIds(array $pluginIds, string $userId): array
    {
        // No ids means nothing can be starred: return before touching the
        // database so an empty page costs no query at all.
        if ($pluginIds === []) {
            return [];
        }

        return DB::table('stars')
            ->where('user_id', $userId)
            ->whereIn('plugin_id', $pluginIds)
            ->pluck('plugin_id')
            ->all();
    }

    public function insertIgnore(string $pluginId, string $userId): bool
    {
        return DB::table('stars')
            ->insertOrIgnore([
                'plugin_id' => $pluginId,
                'user_id' => $userId,
            ]) === 1;
    }

    public function deleteBy(string $pluginId, string $userId): bool
    {
        return DB::table('stars')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->delete() === 1;
    }
}
