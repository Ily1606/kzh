<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginRequest;
use App\Http\Resources\PluginResource;
use App\Services\PluginService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

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
}
