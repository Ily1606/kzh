<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginRequest;
use App\Http\Requests\Plugin\GetTrendingPluginsRequest;
use App\Http\Requests\Plugin\ListPluginsRequest;
use App\Http\Resources\PluginResource;
use App\Http\Resources\PluginViewResource;
use App\Services\PluginService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PluginController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function index(ListPluginsRequest $request): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', config('plugins.pagination.default_per_page'));

        $paginator = $this->pluginService->getPaginatedApprovedPlugins($perPage);

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
                ]
            ]
        );
    }

    /**
     * Submit a new plugin for review.
     *
     * The plugin is created in the `pending` status and is only visible to other
     * users once an administrator approves it. The request is rate limited per
     * authenticated user.
     *
     * Responses:
     * - 201: the plugin was created and is pending review.
     * - 401: the request is not authenticated.
     * - 422: validation failed, or the plugin name is already taken.
     *
     * The 201 and 422 responses are inferred by Scramble from the return value and
     * the validation rules on SubmitPluginRequest, so they are not declared
     * explicitly. The 429 response has to be declared because it comes from the
     * `throttle:submit-plugin` middleware, which Scramble does not track.
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

    public function trackView(Request $request, string $id): JsonResponse
    {
        $viewerId = (string) ($request->user('sanctum')?->getAuthIdentifier() ?? 'guest:' . sha1($request->ip() . '|' . $request->userAgent()));
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

    public function trending(GetTrendingPluginsRequest $request): JsonResponse
    {
        $limit = (int) $request->validated('limit', config('plugins.trending_api.default_limit'));

        $plugins = $this->pluginService->getTrendingPlugins($limit);

        return ApiResponse::successResponse([
            'plugins' => PluginResource::collection($plugins)->resolve(),
        ]);
    }
}
