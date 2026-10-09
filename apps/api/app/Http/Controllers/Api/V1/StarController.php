<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetStarRequest;
use App\Http\Requests\Api\V1\TimelineStarRequest;
use App\Services\StarService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class StarController extends Controller
{
    public function __construct(
        private readonly StarService $starService,
    ) {}

    /**
     * Star or unstar a plugin
     */
    #[Response(status: 429, description: 'Too many star operations. Retry after the rate limit window resets.')]
    public function store(SetStarRequest $request, string $pluginId): JsonResponse
    {
        $result = $this->starService->setStarred(
            $request->user(),
            $pluginId,
            $request->boolean('starred'),
            RequestContext::fromRequest($request),
        );

        $message = $result['starred']
            ? __('api.plugin_starred_successfully')
            : __('api.plugin_unstarred_successfully');

        return ApiResponse::successResponse($result, $message);
    }

    /**
     * Get star timeline for a plugin
     */
    public function timeline(TimelineStarRequest $request, string $pluginId): JsonResponse
    {
        $timeline = $this->starService->getTimeline($pluginId);

        return ApiResponse::successResponse($timeline);
    }
}
