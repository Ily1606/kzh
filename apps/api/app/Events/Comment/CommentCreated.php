<?php

namespace App\Events\Comment;

use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CommentCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * Identity of the comment, frozen at dispatch time.
     *
     * Scalars, so the queue payload holds no row content at all — the comment
     * body is never part of an audit entry.
     *
     * @var array{comment_id: string, plugin_id: string, author_id: string, parent_comment_id: string|null}
     */
    public readonly array $snapshot;

    public function __construct(
        string $commentId,
        string $pluginId,
        string $authorId,
        ?string $parentCommentId,
        public readonly RequestContext $requestContext,
    ) {
        $this->snapshot = [
            'comment_id' => $commentId,
            'plugin_id' => $pluginId,
            'author_id' => $authorId,
            'parent_comment_id' => $parentCommentId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            ...$this->snapshot,
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
