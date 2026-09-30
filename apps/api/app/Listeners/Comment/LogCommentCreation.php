<?php

namespace App\Listeners\Comment;

use App\Events\Comment\CommentCreated;
use App\Listeners\Concerns\ResolvesAuditLogChannel;
use App\Listeners\Concerns\ResolvesRetryPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LogCommentCreation implements ShouldQueue
{
    use ResolvesAuditLogChannel;
    use ResolvesRetryPolicy;

    public function handle(CommentCreated $event): void
    {
        Log::channel($this->auditChannel())
            ->info('Comment created.', $event->context());
    }

    /**
     * Called once the job has exhausted every attempt.
     *
     * Reported on a channel of its own, for the same reason as the auth and
     * plugin listeners: the audit channel is the one that just failed. See
     * ResolvesAuditLogChannel.
     */
    public function failed(CommentCreated $event, Throwable $e): void
    {
        try {
            Log::channel($this->failureChannel())->error('Comment audit logging failed.', [
                'comment_id' => $event->comment->getKey(),
                'plugin_id' => $event->comment->plugin_id,
                'author_id' => $event->author->getKey(),
                'ip_address' => $event->requestContext->ipAddress,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable) {
            // Last resort: never let the failure reporter itself throw.
            error_log('Comment audit logging failed: '.$e->getMessage());
        }
    }

    public function viaQueue(): string
    {
        return (string) config('queue.comment_queue', 'default');
    }

    protected function auditChannelConfigKey(): string
    {
        return 'logging.comment_channel';
    }

    protected function failureChannelConfigKey(): string
    {
        return 'logging.comment_failure_channel';
    }
}
