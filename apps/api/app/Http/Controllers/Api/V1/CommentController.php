<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateCommentRequest;
use App\Http\Requests\Api\V1\ListCommentsRequest;
use App\Http\Resources\CommentResource;
use App\Services\CommentService;
use App\Support\ApiResponse;
use App\Support\RequestContext;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    /**
     * Paginated list of the top-level comments of an approved plugin.
     */
    #[Response(status: 404, description: 'Plugin not found or not approved.')]
    public function index(ListCommentsRequest $request, string $pluginId): JsonResponse
    {
        $comments = $this->commentService->getRootComments(
            $pluginId,
            $request->perPage(),
            $request->sort(),
        );

        return ApiResponse::successResponse(
            ['comments' => CommentResource::collection($comments->items())->resolve()],
            meta: ApiResponse::paginationMeta($comments),
        );
    }

    /**
     * Paginated list of the direct replies of a single comment.
     */
    #[Response(status: 404, description: 'Plugin or parent comment not found or not visible')]
    public function replies(ListCommentsRequest $request, string $pluginId, string $commentId): JsonResponse
    {
        $replies = $this->commentService->getReplies(
            $pluginId,
            $commentId,
            $request->perPage(),
            $request->sort(),
        );

        return ApiResponse::successResponse(
            ['comments' => CommentResource::collection($replies->items())->resolve()],
            meta: ApiResponse::paginationMeta($replies),
        );
    }

    /**
     * Create a new comment on an approved plugin
     */
    #[Response(status: 429, description: 'Too many comments. Retry after the rate limit window resets.')]
    public function store(CreateCommentRequest $request, string $pluginId): JsonResponse
    {
        $comment = $this->commentService->create(
            $request->user(),
            $pluginId,
            $request->validated(),
            RequestContext::fromRequest($request),
        );

        return ApiResponse::successResponse(
            ['comment' => new CommentResource($comment)],
            __('api.comment_created_successfully'),
            201,
        );
    }
}
