<?php

namespace Tests\Feature\Api\V1;

use App\Events\Comment\CommentCreated;
use App\Listeners\Comment\LogCommentCreation;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Comment-specific guards for the audit listener.
 *
 * The retry policy and the audit/failure channel split both live in the shared
 * traits, and are covered once per trait by AuthAuditFailureHandlingTest and
 * PluginAuditFailureHandlingTest. Re-running that matrix here would only prove
 * the traits still work, so what is left is what is specific to comments: the
 * identifiers the report carries, and the fact that it is still produced once
 * the rows behind it are gone.
 */
class CommentAuditFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The identifiers are the listener's own concern — the traits do not know
     * what a comment is. Driven through CallQueuedListener::failed(), the same
     * path a worker takes on the final attempt, which keeps the argument order
     * honest too.
     */
    public function test_the_failure_report_carries_the_comment_audit_identifiers(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();
        $comment = Comment::factory()->create(['author_id' => $user->getKey()]);

        $event = new CommentCreated(
            commentId: $comment->getKey(),
            pluginId: $comment->plugin_id,
            authorId: $user->getKey(),
            parentCommentId: null,
            requestContext: new RequestContext('198.51.100.7', 'Comment Review Agent'),
        );

        (new CallQueuedListener(LogCommentCreation::class, 'handle', [$event]))
            ->failed(new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);

        $failure = $entries[0];

        $this->assertSame('Comment audit logging failed.', $failure->message);
        $this->assertSame('error', $failure->level);
        $this->assertSame($comment->getKey(), $failure->context['comment_id']);
        $this->assertSame($comment->plugin_id, $failure->context['plugin_id']);
        $this->assertSame($user->getKey(), $failure->context['author_id']);
        $this->assertSame('198.51.100.7', $failure->context['ip_address']);
        $this->assertSame(RuntimeException::class, $failure->context['exception']);
        $this->assertSame('log sink unreachable', $failure->context['error']);
    }

    /**
     * The reporter runs on the failure path, so it must not depend on any row
     * still being there. A comment disappears when its plugin is deleted — the
     * FK cascades — and the retry backoff is 10s/60s, so by the time a job is
     * finally declared failed the comment is routinely gone.
     *
     * Reading context() or a model here would throw on exactly that path,
     * turning a logged failure into a silent one: the report is the only trace
     * left, and it is written from inside the failure handler. So the event
     * carries dispatch-time scalars, and the report comes out complete even
     * though nothing can be queried any more.
     */
    public function test_the_failure_handler_reports_even_after_the_rows_are_gone(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->getKey(),
        ]);

        $event = new CommentCreated(
            commentId: $comment->getKey(),
            pluginId: $plugin->id,
            authorId: $user->getKey(),
            parentCommentId: null,
            requestContext: new RequestContext('198.51.100.7', null),
        );

        // Both rows are gone by the time the job runs out of attempts. Plugin is
        // soft-deleting, so it takes forceDelete() to actually remove the row
        // and let the FK cascade reach the comments.
        $plugin->forceDelete();

        $this->assertDatabaseMissing('comments', ['id' => $comment->getKey()]);

        // The author is gone too: users soft-delete as well.
        $user->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);

        (new LogCommentCreation)->failed(
            unserialize(serialize($event)),
            new RuntimeException('log sink unreachable'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('Comment audit logging failed.', $entries[0]->message);
        $this->assertSame($comment->getKey(), $entries[0]->context['comment_id']);
        $this->assertSame($plugin->id, $entries[0]->context['plugin_id']);
        $this->assertSame($user->getKey(), $entries[0]->context['author_id']);
        $this->assertSame('198.51.100.7', $entries[0]->context['ip_address']);
        $this->assertSame('log sink unreachable', $entries[0]->context['error']);
    }
}
