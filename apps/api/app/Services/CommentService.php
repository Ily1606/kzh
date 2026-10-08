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
use Illuminate\Validation\ValidationException;

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
     *
     * TODO(KDN-1538): only the comment passed in is checked for visibility, not
     * its ancestors. With a hidden root A, a caller who knows the id of a reply B
     * can still read `B`'s replies. Whatever moderation endpoint lands with
     * KDN-1538 owns this: either walk the chain of ancestors (one recursive CTE
     * up from the comment is enough — verified at ~7ms for a 51-level chain) or
     * propagate `hidden_at` to the subtree and drop the lookup.
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
     *
     * @throws ValidationException When the parent sits at `comments.max_depth`,
     *                             so the reply would be one level too deep.
     */
    public function create(User $user, string $pluginId, array $data, RequestContext $requestContext): Comment
    {
        // Ensure the plugin exists and is approved.
        $plugin = $this->pluginRepository->findApprovedById($pluginId);

        $parentId = $data['parent_comment_id'] ?? null;
        $maxDepth = max(1, (int) config('comments.max_depth'));

        $comment = DB::transaction(function () use ($user, $plugin, $parentId, $maxDepth, $data): Comment {
            // If replying to a parent, verify it belongs to the same plugin.
            //
            // The check and the insert share one transaction, and the parent row is
            // locked `for update` first: a concurrent hide or delete of that comment
            // now either waits for this transaction or happens before it, so it can
            // no longer land between the check and the insert. Left unlocked, a hard
            // delete in that gap would break the foreign key on insert and surface as
            // a 500 instead of the documented 404.
            //
            // TODO(KDN-1538): the lock makes this check race-free, not
            // ancestor-aware — it proves the parent is visible right now, but not
            // that nothing above it is hidden. Same gap as in getReplies().
            if ($parentId !== null) {
                $parent = $this->commentRepository->findVisibleByIdAndPlugin($parentId, $plugin->id, true);

                $this->assertWithinDepthLimit($parent, $maxDepth);
            }

            $comment = $this->commentRepository->create([
                'plugin_id' => $plugin->id,
                'author_id' => $user->getAuthIdentifier(),
                'parent_comment_id' => $parentId,
                'content' => $data['content'],
            ]);

            $this->pluginRepository->incrementCommentCount($plugin->id);
            TrendingTracker::trackComment($plugin->id);

            return $comment;
        });

        $comment->load('author.profile');

        CommentCreated::dispatch(
            commentId: $comment->getKey(),
            pluginId: $comment->plugin_id,
            authorId: $comment->author_id,
            parentCommentId: $comment->parent_comment_id,
            requestContext: $requestContext,
        );

        return $comment;
    }

    /**
     * Reject a reply that would push the thread past `comments.max_depth`.
     *
     * The new comment's depth is its parent's depth plus one, so the check is a
     * single comparison on the parent — no walking the tree from the new
     * comment's side and no counter to keep in sync with the limit. The walk is
     * capped at `$maxDepth` because a parent already at the limit is rejected
     * whatever its real depth is, and stopping there bounds the queries.
     *
     * Raised as a validation error rather than a bare exception so the client
     * gets a 422 with the message attached to `parent_comment_id` — the field it
     * actually sent — instead of an opaque 500 from `throw new Error()`.
     *
     * @throws ValidationException
     */
    private function assertWithinDepthLimit(Comment $parent, int $maxDepth): void
    {
        // depthOf(parent_comment_id, maxDepth)
        if ($this->commentRepository->depthOf($parent->getKey(), $maxDepth) + 1 <= $maxDepth) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_comment_id' => [__('api.comment_max_depth_reached')],
        ]);
    }
}
