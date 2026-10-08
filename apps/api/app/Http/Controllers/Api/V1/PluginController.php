<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginRequest;
use App\Http\Requests\Plugin\ListPluginsRequest;
use App\Http\Requests\Api\V1\UpdatePluginRequest;
use App\Http\Resources\PluginResource;
use App\Http\Resources\PluginViewResource;
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

    public function index(ListPluginsRequest $request): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', config('plugins.pagination.default_per_page'));

        $paginator = $this->pluginService->getPaginatedApprovedPlugins(
            $perPage,
            Auth::user()
        );

        return ApiResponse::successResponse(
            PluginResource::collection($paginator)->resolve(),
            'Plugins retrieved successfully.',
            200,
            [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]
        );
    }

    public function show(string $id): JsonResponse
    {
        $plugin = $this->pluginService->getPlugin($id);

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
        $viewerId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest:'.sha1($request->ip().'|'.$request->userAgent()));
        $result = $this->pluginService->incrementViewIfNotViewed($id, $viewerId);
        $secondsInHour = 3600.0;

        $ttl = (int) config('plugins.view_cache_ttl');
        $message = $result->counted
            ? __('api.plugin_view_counted')
            : __('api.plugin_view_already_counted', ['hours' => max(1, (int) round($ttl / $secondsInHour))]);

        return ApiResponse::successResponse(
            (new PluginViewResource($result))->resolve(),
            $message
        );
    }

    /**
     * Trending
     *
     * @unauthenticated
     */
    public function trending(ListPluginsRequest $request): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', config('plugins.pagination.default_per_page'));

        $paginator = $this->pluginService->getTrendingPlugins(
            $perPage,
            Auth::user()
        );

        return ApiResponse::successResponse(
            PluginResource::collection($paginator)->resolve(),
            'Trending plugins retrieved successfully.',
            200,
            [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]
        );
    }
}
