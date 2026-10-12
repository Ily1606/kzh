<?php

namespace App\Repositories;

use App\Contracts\PluginEventRepositoryInterface;
use App\Enums\PluginEventType;
use App\Models\PluginEvent;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<PluginEvent>
 */
class PluginEventRepository extends BaseRepository implements PluginEventRepositoryInterface
{
    public function getModel(): string
    {
        return PluginEvent::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PluginEvent
    {
        /** @var PluginEvent $event */
        $event = parent::create($attributes);

        return $event;
    }

    public function getPaginatedForPlugin(string $pluginId, string $sort, int $perPage): LengthAwarePaginator
    {
        $oldest = $sort === 'oldest';

        return $this->newQuery()
            ->where('plugin_id', $pluginId)
            // `created_at` is second-precision, so ties are routine, and SQL
            // leaves equal values unordered. Pin them, or the query plan
            // decides — and rows repeat across pages when it changes.
            ->orderBy('created_at', $oldest ? 'asc' : 'desc')
            ->orderBy('id', $oldest ? 'asc' : 'desc')
            ->paginate($perPage);
    }

    public function record(
        string $pluginId,
        PluginEventType $eventType,
        ?string $message = null,
        ?string $adminId = null,
    ): PluginEvent {
        return $this->create([
            'plugin_id' => $pluginId,
            'event_type' => $eventType,
            'message' => $message,
            'admin_id' => $adminId,
        ]);
    }
}
