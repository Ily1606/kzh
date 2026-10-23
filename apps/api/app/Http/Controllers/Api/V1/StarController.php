<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetStarRequest;
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

        return $this->respond($result);
    }

    /**
     * Star or unstar a plugin, addressed by its author and name.
     */
    #[Response(status: 429, description: 'Too many star operations. Retry after the rate limit window resets.')]
    public function storeBySlug(SetStarRequest $request, string $username, string $pluginName): JsonResponse
    {
        $result = $this->starService->setStarredBySlug(
            $request->user(),
            $username,
            $pluginName,
            $request->boolean('starred'),
            RequestContext::fromRequest($request),
        );

        return $this->respond($result);
    }

    /**
     * @param  array{starred: bool, star_count: int}  $result
     */
    private function respond(array $result): JsonResponse
    {
        $message = $result['starred']
            ? __('api.plugin_starred_successfully')
            : __('api.plugin_unstarred_successfully');

        return ApiResponse::successResponse($result, $message);
    }

    /**
     * Get star timeline for a plugin
     */
    public function timeline(string $pluginId): JsonResponse
    {
        $timeline = $this->starService->getTimeline($pluginId);

        return ApiResponse::successResponse($timeline);
    }
}
