<?php

namespace App\Repositories;

use App\Contracts\PluginDailyViewRepositoryInterface;
use App\Models\PluginDailyView;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<PluginDailyView>
 */
class PluginDailyViewRepository extends BaseRepository implements PluginDailyViewRepositoryInterface
{
    public function getModel(): string
    {
        return PluginDailyView::class;
    }

    public function getDailyViewCounts(int $daysLimit): Collection
    {
        return $this->newQuery()
            ->select('plugin_id', 'date', 'views_count as count')
            ->where('date', '>=', now()->subDays($daysLimit)->format('Y-m-d'))
            ->get();
    }
}

