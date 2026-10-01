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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards the retry policy of the comment audit listener.
 *
 * A creation audit entry records who commented on what, so a transient logging
 * failure must be retried rather than silently dropped, and a terminal failure
 * has to leave a trace of its own.
 */
class CommentAuditFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_listener_declares_a_retry_policy(): void
    {
        $listener = new LogCommentCreation;

        $this->assertSame(3, $listener->tries());
        $this->assertSame([10, 60], $listener->backoff());
    }

    /**
     * The policy is read from config rather than hard coded, so the same listener
     * can be tuned per environment. This also guards the job payload, which is
     * what the worker actually obeys.
     */
    public function test_the_retry_policy_follows_the_configuration(): void
    {
        Bus::fake();

        config()->set('queue.audit_retry.tries', 7);
        config()->set('queue.audit_retry.backoff', '5,30,90');

        CommentCreated::dispatch(
            commentId: '00000000-0000-0000-0000-000000000001',
            pluginId: '00000000-0000-0000-0000-000000000002',
            authorId: '00000000-0000-0000-0000-000000000003',
            parentCommentId: null,
            requestContext: new RequestContext(null, null),
        );

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(7, $job->tries);
        $this->assertSame([5, 30, 90], $job->backoff);
    }

    /**
     * The env value is a comma separated string, the array form is accepted too so
     * a config override in a test or a seeder does not need the string syntax.
     */
    public function test_the_backoff_accepts_both_an_array_and_a_comma_separated_string(): void
    {
        config()->set('queue.audit_retry.backoff', [5, 30]);
        $this->assertSame([5, 30], (new LogCommentCreation)->backoff());

        config()->set('queue.audit_retry.backoff', '5, 30 ,90');
        $this->assertSame([5, 30, 90], (new LogCommentCreation)->backoff());
    }

    public function test_the_retry_policy_reaches_the_queued_job(): void
    {
        Bus::fake();

        CommentCreated::dispatch(
            commentId: '00000000-0000-0000-0000-000000000001',
            pluginId: '00000000-0000-0000-0000-000000000002',
            authorId: '00000000-0000-0000-0000-000000000003',
            parentCommentId: null,
            requestContext: new RequestContext(null, null),
        );

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }

    /**
     * On the final attempt the worker goes through CallQueuedListener, which
     * appends the throwable to the queued payload before calling
     * failed($event, $e). Driving that same path keeps the argument order
     * honest and pins the report an operator actually receives.
     */
    public function test_an_exhausted_job_reports_the_failure_with_enough_context(): void
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

    /**
     * The same regression the auth and plugin listeners guard: comment_channel is
     * null whenever LOG_COMMENT_CHANNEL is unset, and Laravel resolves that null
     * to logging.default, so one broken sink would take down both the audit entry
     * and the report that it had failed.
     */
    public function test_the_failure_is_not_reported_on_the_channel_that_just_failed(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.comment_channel', null);
        config()->set('logging.comment_failure_channel', 'stderr');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        (new LogCommentCreation)->failed(
            new CommentCreated(
                commentId: '00000000-0000-0000-0000-000000000001',
                pluginId: '00000000-0000-0000-0000-000000000002',
                authorId: '00000000-0000-0000-0000-000000000003',
                parentCommentId: null,
                requestContext: new RequestContext(null, null),
            ),
            new RuntimeException('log sink unreachable'),
        );
    }

    public function test_the_failure_channel_follows_the_configuration(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.comment_channel', 'daily');
        config()->set('logging.comment_failure_channel', 'syslog');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('syslog')->andReturn($logger);

        (new LogCommentCreation)->failed(
            new CommentCreated(
                commentId: '00000000-0000-0000-0000-000000000001',
                pluginId: '00000000-0000-0000-0000-000000000002',
                authorId: '00000000-0000-0000-0000-000000000003',
                parentCommentId: null,
                requestContext: new RequestContext('198.51.100.7', null),
            ),
            new RuntimeException('log sink unreachable'),
        );
    }

    /**
     * An operator can point the failure channel at the audit channel by hand,
     * which would defeat the whole point. The resolver steps over it.
     */
    public function test_a_failure_channel_pointing_at_the_audit_channel_is_skipped(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.comment_channel', 'daily');
        config()->set('logging.comment_failure_channel', 'daily');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        (new LogCommentCreation)->failed(
            new CommentCreated(
                commentId: '00000000-0000-0000-0000-000000000001',
                pluginId: '00000000-0000-0000-0000-000000000002',
                authorId: '00000000-0000-0000-0000-000000000003',
                parentCommentId: null,
                requestContext: new RequestContext('198.51.100.7', null),
            ),
            new RuntimeException('log sink unreachable'),
        );
    }

    /**
     * Even if the rescue channel itself is down, failed() must not throw —
     * a terminal failure has to leave a trace, never raise a new exception.
     */
    public function test_failed_never_throws_when_the_failure_channel_is_down(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.comment_channel', null);
        config()->set('logging.comment_failure_channel', 'stderr');

        Log::shouldReceive('channel')->once()->with('stderr')->andThrow(new RuntimeException('stderr down'));

        (new LogCommentCreation)->failed(
            new CommentCreated(
                commentId: '00000000-0000-0000-0000-000000000001',
                pluginId: '00000000-0000-0000-0000-000000000002',
                authorId: '00000000-0000-0000-0000-000000000003',
                parentCommentId: null,
                requestContext: new RequestContext('198.51.100.7', null),
            ),
            new RuntimeException('log sink unreachable'),
        );

        $this->assertTrue(true);
    }
}
