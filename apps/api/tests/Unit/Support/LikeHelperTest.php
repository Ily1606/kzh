<?php

namespace Tests\Unit\Support;

use App\Models\Plugin;
use App\Support\LikeHelper;
use Illuminate\Database\Eloquent\Builder;
use PDO;
use Tests\TestCase;
use Throwable;

class LikeHelperTest extends TestCase
{
    public function test_it_uses_ilike_on_postgres(): void
    {
        $sql = LikeHelper::whereContains(Plugin::on('pgsql'), 'name', 'laravel')->toSql();

        $this->assertStringContainsString('ILIKE', $sql);
    }

    public function test_it_uses_like_on_sqlite(): void
    {
        $sql = LikeHelper::whereContains(Plugin::on('sqlite'), 'name', 'laravel')->toSql();

        $this->assertStringContainsString(' LIKE ', $sql);
    }

    public function test_it_escapes_like_wildcards(): void
    {
        $query = LikeHelper::whereContains(Plugin::on('sqlite'), 'name', '100%_!');

        $this->assertSame(['%100!%!_!!%'], $query->getBindings());
    }

    public function test_the_search_query_runs_on_postgres(): void
    {
        $config = config('database.connections.pgsql');

        try {
            $pdo = new PDO(
                sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'], $config['port'] ?: 5432),
                $config['username'],
                $config['password'],
            );
        } catch (Throwable) {
            $this->markTestSkipped('PostgreSQL is not reachable.');
        }

        $pdo->exec('create temp table plugins (name text, title text, deleted_at timestamp)');
        $pdo->exec("insert into plugins values ('100%', '100%'), ('100x', '100x')");

        $query = LikeHelper::whereContains(Plugin::on('pgsql'), 'name', '100%')
            ->orWhere(fn (Builder $title) => LikeHelper::whereContains($title, 'title', '100%'));

        // `ESCAPE '\'` used to make pdo_pgsql lose the second placeholder here (HY093).
        $statement = $pdo->prepare($query->toSql());
        $statement->execute($query->getBindings());

        $this->assertSame(['100%'], $statement->fetchAll(PDO::FETCH_COLUMN, 0));
    }
}
