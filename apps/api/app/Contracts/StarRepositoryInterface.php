<?php

namespace App\Contracts;

interface StarRepositoryInterface
{
    /**
     * Whether this user currently stars this plugin.
     *
     * Read-only, no row locking: nothing is about to be written based on the
     * answer, so there is no window for a lock to protect.
     */
    public function isStarred(string $pluginId, string $userId): bool;

    /**
     * Insert the star, reporting whether a row was actually created.
     *
     * Returns `true` ONLY when a new row was inserted. The caller bumps the
     * plugin counter on that result alone, which is what makes starring
     * idempotent: a second `starred: true` for a pair that already exists
     * inserts nothing and therefore moves no counter.
     *
     * The return value is the row count, deliberately, not a read-then-write.
     * Checking existence first and then inserting leaves a window in which two
     * concurrent requests both see "not starred" and both insert — the loser
     * loses the primary key race and surfaces as a 500. Asking the database how
     * many rows the statement affected turns that race into a no-op instead.
     *
     * The SQL is not the same on every driver — PostgreSQL compiles this to
     * `ON CONFLICT DO NOTHING`, SQLite to `INSERT OR IGNORE` — but both return
     * the affected row count, which is the only thing callers rely on.
     */
    public function insertIgnore(string $pluginId, string $userId): bool;

    /**
     * Remove the star, reporting whether a row was actually deleted.
     *
     * Returns `true` ONLY when a row existed and was removed. This is what makes
     * unstarring idempotent: a `starred: false` for a plugin the user never
     * starred is a normal outcome, not a missing resource, so the caller
     * decrements the counter on this result alone.
     */
    public function deleteBy(string $pluginId, string $userId): bool;
}
