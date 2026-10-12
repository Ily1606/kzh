<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPluginEventsRequest;
use App\Http\Resources\PluginEventResource;
use App\Models\Plugin;
use App\Services\PluginEventService;
use App\Services\PluginService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PluginEventController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
        private readonly PluginEventService $pluginEventService,
    ) {}

    /**
     * The review timeline of one plugin: who submitted it, what the admin
     * decided, and every revision the owner sent back.
     */
    #[Response(status: 404, description: 'The plugin does not exist, is soft-deleted, is not visible to the caller, or is not the caller\'s.')]
    public function index(ListPluginEventsRequest $request, string $pluginId): JsonResponse
    {
        $plugin = $this->pluginService->getPlugin($pluginId, $request->user());

        if ($plugin->user_id !== $request->user()->getAuthIdentifier()) {
            throw (new ModelNotFoundException)->setModel(Plugin::class, [$pluginId]);
        }

        $events = $this->pluginEventService->paginateForPlugin(
            $pluginId,
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::successResponse(
            ['events' => PluginEventResource::collection($events->items())->resolve()],
            meta: ApiResponse::paginationMeta($events),
        );
    }
}
