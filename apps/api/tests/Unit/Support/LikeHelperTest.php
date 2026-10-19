<?php

namespace Tests\Unit\Support;

use App\Models\Plugin;
use App\Support\LikeHelper;
use Tests\TestCase;

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
}
