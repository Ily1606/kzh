<?php

namespace App\Repositories;

use App\Contracts\StarRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the `stars` join table.
 *
 * The table has a composite primary key `(plugin_id, user_id)`. Eloquent does
 * not support composite keys — `Model::$primaryKey` holds a single value, there
 * is no way to declare a multi-column key — so there is no `App\Models\Star`
 * and this class deliberately does NOT extend `BaseRepository`: that base class
 * exists to own a lifecycle around a single Eloquent model, which is exactly
 * what is unavailable here. The query builder covers everything this table
 * needs.
 *
 * The absence of a model is why there is no `StarFactory` either. Tests seed
 * stars with `DB::table('stars')->insert([...])`.
 *
 * If a "list the plugins I starred" endpoint is added later, revisit this: an
 * Eloquent `hasMany` relation CAN be declared over a composite-PK table, since
 * a relation only needs the foreign key column, not a primary key.
 */
final class StarRepository implements StarRepositoryInterface
{
    public function isStarred(string $pluginId, string $userId): bool
    {
        return DB::table('stars')
            ->where('plugin_id', $pluginId)
            ->where('user_id', $userId)
            ->exists();
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
