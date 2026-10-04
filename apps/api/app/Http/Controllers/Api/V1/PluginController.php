<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

class PluginController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $defaultPerPage = config('plugins.pagination.default_per_page');
        $maxPerPage = config('plugins.pagination.max_per_page');

        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));

        $paginator = $this->pluginService->getPaginatedApprovedPlugins(
            $perPage,
            $request->user('sanctum'),
        );

        return PluginResource::collection($paginator);
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

    /**
     * Update a plugin you own.
     * 
     * Editing does not affect review state: an approved plugin stays approved
     * with its original `approved_at`.
     *
     * Responses:
     * - 200: the updated plugin in `data.plugin`.
     * - 401: the request is not authenticated.
     * - 403: the plugin belongs to another user.
     * - 404: the plugin does not exist or is soft-deleted.
     * - 422: validation failed, no editable field was sent, or the name is taken.
     *
     * The 200 and 422 responses are inferred by Scramble from the return value
     * and the validation rules on UpdatePluginRequest, so they are not declared
     * explicitly. No 429 is declared because this route rides the shared
     * `throttle:api` limiter rather than one of its own, exactly like
     * `PATCH /user`.
     */
    public function update(UpdatePluginRequest $request, string $pluginId): JsonResponse
    {
        $plugin = $this->pluginService->update(
            $request->user(),
            $pluginId,
            $request->validated(),
        );

        return ApiResponse::successResponse(
            ['plugin' => new PluginResource($plugin)],
            __('api.plugin_updated_successfully'),
        );
    }

    public function trackView(Request $request, string $id): JsonResponse
    {
        $result = $this->pluginService->incrementViewIfNotViewed($id, $request);

        return ApiResponse::successResponse($result, $result['message']);
    }

    public function trending(Request $request): JsonResponse
    {
        $defaultLimit = config('plugins.trending_api.default_limit');
        $maxLimit = config('plugins.trending_api.max_limit');

        $limit = (int) $request->query('limit', $defaultLimit);
        $limit = max(1, min($limit, $maxLimit));

        $plugins = $this->pluginService->getTrendingPlugins($limit);

        return ApiResponse::successResponse([
            'plugins' => $plugins,
        ]);
    }
}
