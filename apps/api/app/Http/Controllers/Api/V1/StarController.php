<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetStarRequest;
use App\Services\StarService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StarController extends Controller
{
    public function __construct(
        private readonly StarService $starService,
    ) {}

    /**
     * Whether the authenticated user has starred this plugin.
     *
     * Deliberately reports only `starred`, not the plugin's star count: this
     * endpoint answers "did *I* star this", and `star_count` already ships in
     * PluginResource. Keeping them apart stops a client from reading a count
     * that was never scoped to them.
     *
     * Responses:
     * - 200: the current state in `data.starred`.
     * - 401: unauthenticated.
     * - 404: plugin not found, soft-deleted, or not approved.
     *
     * Takes a plain Request, not SetStarRequest: a read carries no payload, so
     * validating one would make the endpoint reject its own callers.
     */
    public function show(Request $request, string $pluginId): JsonResponse
    {
        $starred = $this->starService->getStarredState($request->user(), $pluginId);

        return ApiResponse::successResponse(['starred' => $starred]);
    }

    /**
     * Star or unstar a plugin (auth required).
     *
     * Set-state, not toggle: the body carries the state the client wants, so a
     * retry or a double tap cannot flip the result. Unstarring a plugin that was
     * never starred is a 200 no-op rather than a 404.
     *
     * Responses:
     * - 200: the state that resulted, plus the plugin's new star count.
     * - 401: unauthenticated.
     * - 404: plugin not found, soft-deleted, or not approved.
     * - 422: `starred` missing or not a boolean.
     *
     * The 200 and 422 responses are inferred by Scramble from the return value
     * and the validation rules on SetStarRequest, so they are not declared
     * explicitly. The 429 has to be declared because it comes from the
     * `throttle:star-plugin` middleware, which Scramble does not track.
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
}
