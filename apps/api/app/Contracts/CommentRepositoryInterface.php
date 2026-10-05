<?php

namespace App\Contracts;

use App\Models\Comment;
use Illuminate\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment;

    /**
     * Paginate the highest-level comments of a plugin (parent_comment_id IS NULL),
     * ordered by the given sort, with the number of visible direct replies
     * computed onto each row.
     */
    public function paginateRootByPlugin(string $pluginId, int $perPage, string $sort): LengthAwarePaginator;

    /**
     * Paginate the direct replies of a single comment, ordered by the given
     * sort, with the number of visible direct replies computed onto each row.
     */
    public function paginateRepliesByParent(string $parentCommentId, int $perPage, string $sort): LengthAwarePaginator;

    /**
     * Find a visible (not hidden, not soft-deleted) comment by ID that belongs to the given plugin.
     *
     * Pass `$lock = true` when the row is about to be written to inside the same
     * transaction: it appends `for update` so a concurrent hide or delete cannot
     * slip in between the check and the write. Outside a transaction the clause is
     * released immediately, so only the create path should set it.
     */
    public function findVisibleByIdAndPlugin(string $id, string $pluginId, bool $lock = false): Comment;

    /**
     * How deep a comment sits in its thread: 1 for a top-level comment, 2 for a
     * reply, 3 for a sub-reply.
     *
     * Walks `parent_comment_id` upward, so the caller can enforce
     * `config('comments.max_depth')` without loading the whole tree. The walk
     * stops at `$stopAt`, which keeps the query count bounded on a tree deeper
     * than the limit — a value already past the limit cannot be brought back
     * under it by walking further.
     *
     * @param  int  $stopAt  Depth at which to stop early and return.
     */
    public function depthOf(string $id, int $stopAt): int;
}
