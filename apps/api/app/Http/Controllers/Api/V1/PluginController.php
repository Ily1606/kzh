<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PluginStatus;
use App\Http\Requests\Api\V1\ListMyPluginsRequest;
use App\Http\Requests\Api\V1\SubmitPluginRequest;
use App\Http\Requests\Api\V1\UpdatePluginRequest;
use App\Http\Resources\PluginResource;
use App\Services\PluginService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PluginController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    /**
     * Get plugin list.
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $defaultPerPage = config('plugins.pagination.default_per_page');
        $maxPerPage = config('plugins.pagination.max_per_page');

        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));

        $paginator = $this->pluginService->getPaginatedApprovedPlugins(
            $perPage,
            Auth::user()
        );

        return PluginResource::collection($paginator);
    }

    /**
     * List the user's plugins
     *
     * Pass `?status=` to narrow the list; omit it to get every status.
     */
    #[Response(status: 422, description: 'The `status` query parameter is not a known review status.')]
    public function myPlugins(ListMyPluginsRequest $request): AnonymousResourceCollection
    {
        $defaultPerPage = config('plugins.pagination.default_per_page');
        $maxPerPage = config('plugins.pagination.max_per_page');

        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));

        $status = $request->validated('status');

        $paginator = $this->pluginService->getPaginatedPluginsByUser(
            $request->user(),
            $status === null ? null : PluginStatus::from($status),
            $perPage,
        );

        return PluginResource::collection($paginator);
    }

    /**
     * Get one plugin's detail.
     *
     * @unauthenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $plugin = $this->pluginService->getPlugin($id, $request->user());

        return ApiResponse::successResponse([
            'plugin' => new PluginResource($plugin),
        ]);
    }

    /**
     * Submit a new plugin for review.
     */
    #[Response(status: 429, description: 'Too many submissions. Retry after the rate limit window resets.')]
    public function store(SubmitPluginRequest $request): JsonResponse
    {
        $plugin = $this->pluginService->submit(
            $request->user(),
            $request->validated(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse(
            ['plugin' => new PluginResource($plugin)],
            __('api.plugin_submitted_successfully'),
            201,
        );
    }

    /**
     * Update a plugin you own.
     */
    #[Response(status: 404, description: 'The plugin does not exist or is soft-deleted.')]
    #[Response(status: 429, description: 'Too many submissions. Retry after the rate limit window resets.')]
    public function update(UpdatePluginRequest $request, string $pluginId): JsonResponse
    {
        $plugin = $this->pluginService->update(
            $request->user(),
            $pluginId,
            $request->validated(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse(
            ['plugin' => new PluginResource($plugin)],
            __('api.plugin_updated_successfully'),
        );
    }

    /**
     * Track view
     *
     * Include request header `Authorization: Bearer <token>` if user logged in (optional).
     *
     * @unauthenticated
     */
    public function trackView(Request $request, string $id): JsonResponse
    {
        $result = $this->pluginService->incrementViewIfNotViewed($id, $request);

        return ApiResponse::successResponse($result, $result['message']);
    }

    /**
     * Trending
     *
     * @unauthenticated
     */
    public function trending(Request $request): JsonResponse
    {
        $defaultLimit = config('plugins.trending_api.default_limit');
        $maxLimit = config('plugins.trending_api.max_limit');

        $limit = (int) $request->query('limit', $defaultLimit);
        $limit = max(1, min($limit, $maxLimit));

        $plugins = $this->pluginService->getTrendingPlugins($limit, $request->user());

        return ApiResponse::successResponse([
            'plugins' => $plugins,
        ]);
    }
}
