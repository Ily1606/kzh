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
     * Paginated list of the highest-level comments of an approved plugin.
     *
     * Reddit-style: only root comments (parent_comment_id IS NULL) come back, so
     * `meta.total` counts threads instead of rows. Every item carries
     * `replies_count`; the client calls `replies()` when the user expands a
     * thread. `per_page` and `sort` (`newest` | `oldest`) are accepted as query
     * parameters and clamped to `comments.pagination.max_per_page`.
     *
     * Responses:
     * - 200: paginated collection of root comments in `data.comments`.
     * - 404: plugin not found or not approved.
     * - 422: invalid `per_page` or `sort`.
     */
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
     *
     * One level deep only — the same endpoint serves every depth, the client just
     * passes the ID of whichever comment it is expanding. A comment is 404 when
     * it does not exist, is hidden, is soft-deleted, or belongs to another
     * plugin.
     *
     * Responses:
     * - 200: paginated collection of direct replies in `data.comments`.
     * - 404: plugin or parent comment not found / not approved / not visible.
     * - 422: invalid `per_page` or `sort`.
     */
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
     * Create a new comment on an approved plugin (auth required).
     *
     * The request is rate limited per authenticated user.
     *
     * Responses:
     * - 201: comment created successfully.
     * - 401: unauthenticated.
     * - 404: plugin not found, not approved, or parent comment not found / belongs to another plugin.
     * - 422: validation failed, or the parent already sits at `comments.max_depth`
     *   (default 3: top-level, reply, sub-reply) so this reply would be a fourth level.
     *
     * The 201 and 422 responses are inferred by Scramble from the return value and
     * the validation rules on CreateCommentRequest, so they are not declared
     * explicitly. The 429 response has to be declared because it comes from the
     * `throttle:create-comment` middleware, which Scramble does not track.
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
