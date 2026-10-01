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
     */
    public function findVisibleByIdAndPlugin(string $id, string $pluginId): Comment;
}
