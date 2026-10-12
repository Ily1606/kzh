<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PluginDailyViewRepositoryInterface
{
    /**
     * Get daily view counts by plugin and date for the last N days.
     *
     * @return Collection<int, object{plugin_id: string, date: string, count: int}>
     */
    public function getDailyViewCounts(int $daysLimit): Collection;
}

