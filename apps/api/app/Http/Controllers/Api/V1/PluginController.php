<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginRequest;
use App\Http\Resources\PluginResource;
use App\Services\PluginService;
use App\Support\ApiResponse;
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

        $paginator = $this->pluginService->getPaginatedApprovedPlugins($perPage);

        return PluginResource::collection($paginator);
    }

    public function store(SubmitPluginRequest $request): JsonResponse
    {
        $plugin = $this->pluginService->submit(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::successResponse(
            ['plugin' => new PluginResource($plugin)],
            __('api.plugin_submitted_successfully'),
            201,
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
