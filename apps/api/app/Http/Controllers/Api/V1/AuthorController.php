<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthorRequest;
use App\Http\Requests\Api\V1\ListAuthorPluginsRequest;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\PluginResource;
use App\Services\PluginService;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthorController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * The public profile of one author.
     *
     * @unauthenticated
     */
    #[Response(status: 404, description: 'No user carries that username.')]
    public function show(AuthorRequest $request, string $username): JsonResponse
    {
        $author = $this->profileService->getProfileByUsername($username);

        return ApiResponse::successResponse(new AuthorResource($author));
    }

    /**
     * The plugins this author has published.
     *
     * @unauthenticated
     */
    #[Response(status: 404, description: 'No user carries that username.')]
    public function plugins(ListAuthorPluginsRequest $request, string $username): AnonymousResourceCollection
    {
        $author = $this->profileService->getProfileByUsername($username);

        $perPage = $request->perPage();

        $paginator = $this->pluginService->getPaginatedApprovedPluginsByUser(
            (string) $author->getAuthIdentifier(),
            $perPage,
        );

        return PluginResource::collection($paginator);
    }
}
