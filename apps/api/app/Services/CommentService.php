<?php

namespace App\Services;

use App\Contracts\CommentRepositoryInterface;
use App\Contracts\PluginRepositoryInterface;
use App\Events\Comment\CommentCreated;
use App\Models\Comment;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CommentService
{
    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly PluginRepositoryInterface $pluginRepository,
    ) {}

    /**
     * Highest-level comments of a plugin, paginated Reddit-style.
     *
     * Only root comments (parent_comment_id IS NULL) are returned, so `meta.total`
     * counts threads rather than rows of the whole tree. Replies are fetched on
     * demand through {@see self::getReplies()}.
     */
    public function getRootComments(string $pluginId, int $perPage, string $sort): LengthAwarePaginator
    {
        // Ensure the plugin exists and is approved before listing its comments.
        $this->pluginRepository->findApprovedById($pluginId);

        return $this->commentRepository->paginateRootByPlugin($pluginId, $perPage, $sort);
    }

    /**
     * Direct replies of a single comment, paginated.
     *
     * The parent is resolved through the plugin so a caller cannot walk a tree of
     * another plugin (nor an unpublished one) by guessing a comment ID.
     */
    public function getReplies(string $pluginId, string $commentId, int $perPage, string $sort): LengthAwarePaginator
    {
        $this->pluginRepository->findApprovedById($pluginId);

        $this->commentRepository->findVisibleByIdAndPlugin($commentId, $pluginId);

        return $this->commentRepository->paginateRepliesByParent($commentId, $perPage, $sort);
    }

    /**
     * @param  array{content: string, parent_comment_id?: string|null}  $data
     * @param  RequestContext  $requestContext  Metadata of the originating request.
     */
    public function create(User $user, string $pluginId, array $data, RequestContext $requestContext): Comment
    {
        // Ensure the plugin exists and is approved.
        $plugin = $this->pluginRepository->findApprovedById($pluginId);

        $parentId = $data['parent_comment_id'] ?? null;

        // If replying to a parent, verify it belongs to the same plugin.
        //
        // Both existence checks stay OUTSIDE the transaction below: a
        // ModelNotFoundException raised inside a transaction closure is swallowed
        // by the rollback and rethrown, which would turn the documented 404 into a
        // 500. Resolving them first keeps the 404 contract intact.
        if (! empty($parentId)) {
            $this->commentRepository->findVisibleByIdAndPlugin($parentId, $plugin->id);
        }

        $comment = DB::transaction(function () use ($user, $plugin, $parentId, $data): Comment {
            $comment = $this->commentRepository->create([
                'plugin_id' => $plugin->id,
                'author_id' => $user->getAuthIdentifier(),
                'parent_comment_id' => $parentId,
                'content' => $data['content'],
            ]);

            // Keep the denormalised counters the list endpoints expose in sync.
            // The insert and both counter updates commit or roll back together, so
            // a failure here can never leave a comment whose counters were never
            // bumped — that drift would be permanent, with no reconciliation job.
            $plugin->increment('comment_count');

            if (! empty($parentId)) {
                $this->commentRepository->incrementRepliesCount($parentId);
            }

            return $comment;
        });

        // `create()` only sets the four attributes it was handed, so the model has
        // no in-memory `replies_count` even though the column exists and defaults
        // to 0. Without a refresh, CommentResource falls through to a null
        // `replies_count` and the 201 payload disagrees with the list endpoints,
        // which read the same field as an integer.
        //
        // refresh() re-reads the row *and* keeps `author.profile` loaded, so the
        // single query here is what buys a consistent payload.
        $comment = $comment->load('author.profile')->refresh();

        CommentCreated::dispatch(
            commentId: $comment->getKey(),
            pluginId: $comment->plugin_id,
            authorId: $comment->author_id,
            parentCommentId: $comment->parent_comment_id,
            requestContext: $requestContext,
        );

        return $comment;
    }
}
