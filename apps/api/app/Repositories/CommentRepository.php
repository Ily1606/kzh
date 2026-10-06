<?php

namespace App\Repositories;

use App\Contracts\CommentRepositoryInterface;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Comment>
 */
class CommentRepository extends BaseRepository implements CommentRepositoryInterface
{
    /**
     * The sort strategies a client may ask for, mapped to the column and
     * direction to order by.
     *
     * This map is the single source of truth: `ListCommentsRequest` validates
     * `sort` against exactly these keys, so an unknown value never reaches
     * {@see self::paginateLevel()} and needs no fallback here.
     *
     * `id` is appended as a tie-breaker on every sort: comments created within
     * the same second are common (bulk seeds, tests, imports) and without a
     * deterministic tie-breaker paginated pages can repeat or skip rows.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
    ];

    public function getModel(): string
    {
        return Comment::class;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment
    {
        /** @var Comment $comment */
        $comment = parent::create($attributes);

        return $comment;
    }

    public function paginateRootByPlugin(string $pluginId, int $perPage, string $sort): LengthAwarePaginator
    {
        return $this->paginateLevel(
            $this->baseQuery()->where('plugin_id', $pluginId)->whereNull('parent_comment_id'),
            $perPage,
            $sort,
        );
    }

    public function paginateRepliesByParent(string $parentCommentId, int $perPage, string $sort): LengthAwarePaginator
    {
        return $this->paginateLevel(
            $this->baseQuery()->where('parent_comment_id', $parentCommentId),
            $perPage,
            $sort,
        );
    }

    public function findVisibleByIdAndPlugin(string $id, string $pluginId, bool $lock = false): Comment
    {
        $query = $this->baseQuery()->where('plugin_id', $pluginId);

        return ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
    }

    /**
     * Shared skeleton for both tree levels: the level filter is supplied by the
     * caller, everything else (visibility, eager loads, reply counter, sort and
     * pagination) is identical so the two endpoints cannot drift apart.
     *
     * @param  Builder<Comment>  $query  Query already scoped to one tree level.
     */
    private function paginateLevel(Builder $query, int $perPage, string $sort): LengthAwarePaginator
    {
        [$column, $direction] = self::SORTS[$sort];

        return $query
            ->with('author.profile')
            ->withCount(['replies as visible_replies_count' => fn (Builder $replies) => $replies->visible()])
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage);
    }

    /**
     * Walk `parent_comment_id` upward, counting levels until the root is
     * reached or `$stopAt` is hit.
     *
     * Each step is one indexed lookup on `parent_comment_id`, and the loop is
     * bounded by `$stopAt` rather than by "until no parent is found" — that
     * bound is what makes it safe against a cycle in the data (a comment
     * eventually pointing back at one of its own ancestors), which the
     * unbounded form would spin on forever.
     */
    public function depthOf(string $id, int $stopAt): int
    {
        $depth = 1;
        $currentId = $id;

        while ($depth < $stopAt) {
            // find the comment has ID = $currentId and return its parent_comment_id
            /*
                SELECT parent_comment_id
                FROM comments
                WHERE id = $currentId
                LIMIT 1;
            */
            $parentId = $this->model->newQuery()
                ->whereKey($currentId)
                ->value('parent_comment_id');

            if ($parentId === null) {
                break;
            }

            $depth++;
            $currentId = $parentId;
        }

        return $depth;
    }

    /**
     * @return Builder<Comment>
     */
    private function baseQuery(): Builder
    {
        return $this->model->newQuery()->visible();
    }
}
