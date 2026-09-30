<?php

namespace App\Events\Comment;

use App\Models\Comment;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a comment (or reply) has been persisted successfully.
 */
final class CommentCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Comment $comment,
        public readonly User $author,
        public readonly RequestContext $requestContext,
    ) {}

    public function author(): User
    {
        return $this->author;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'comment_id' => $this->comment->getKey(),
            'plugin_id' => $this->comment->plugin_id,
            'author_id' => $this->author->getKey(),
            'parent_comment_id' => $this->comment->parent_comment_id,
            'ip_address' => $this->requestContext->ipAddress,
            'user_agent' => $this->requestContext->userAgent,
        ];
    }
}
