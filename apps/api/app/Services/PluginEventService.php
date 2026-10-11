<?php

namespace App\Services;

use App\Contracts\PluginEventRepositoryInterface;
use App\Enums\PluginEventType;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PluginEventService
{
    public function __construct(
        private readonly PluginEventRepositoryInterface $pluginEventRepository,
    ) {}

    /**
     * Record an owner-side event.
     */
    public function recordOwnerAction(Plugin $plugin, PluginEventType $eventType): PluginEvent
    {
        return $this->pluginEventRepository->record(
            $plugin->getKey(),
            $eventType,
            message: null,
            adminId: null,
        );
    }

    public function review(
        Plugin $plugin,
        PluginEventType $eventType,
        User $admin,
        ?string $message = null,
        ?callable $write = null,
    ): PluginEvent {
        return DB::transaction(function () use ($plugin, $eventType, $admin, $message, $write): PluginEvent {
            $write?->__invoke($plugin);

            return $this->pluginEventRepository->record(
                $plugin->getKey(),
                $eventType,
                $message,
                $admin->getAuthIdentifier(),
            );
        });
    }

    /**
     * One plugin's timeline, paginated.
     */
    public function paginateForPlugin(string $pluginId, string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->pluginEventRepository->getPaginatedForPlugin($pluginId, $sort, $perPage);
    }
}
