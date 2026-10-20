<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class LikeHelper
{
    /**
     * Constrain a column to a literal substring match.
     *
     * A `%` or `_` typed into a search box would otherwise widen the pattern
     * and match rows the reader never asked for. `ESCAPE` is named explicitly
     * because SQLite — which the test suite runs on — has no default escape
     * character for `like`, unlike PostgreSQL.
     */
    public static function whereContains(Builder $query, string $column, string $term): Builder
    {
        $wrapped = $query->getQuery()->grammar->wrap($column);
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        // PostgreSQL's LIKE is case-sensitive; ILIKE keeps search case-insensitive like whereLike() did.
        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query->whereRaw("{$wrapped} {$operator} ? ESCAPE '!'", [$pattern]);
    }
}
