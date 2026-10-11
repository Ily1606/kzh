<?php

namespace App\Contracts;

use App\Enums\PluginEventType;
use App\Models\PluginEvent;
use Illuminate\Pagination\LengthAwarePaginator;

interface PluginEventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PluginEvent;

    /**
     * One plugin's timeline
     */
    public function getPaginatedForPlugin(string $pluginId, string $sort, int $perPage): LengthAwarePaginator;

    /**
     * Record an event of the given type against a plugin.
     */
    public function record(
        string $pluginId,
        PluginEventType $eventType,
        ?string $message = null,
        ?string $adminId = null,
    ): PluginEvent;
}
