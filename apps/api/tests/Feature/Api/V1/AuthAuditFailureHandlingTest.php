<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\RecordsAuthActivity;
use App\Enums\AuthEventType;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\LogAuthActivity;
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
 * Guards the retry policy of the auth audit listener.
 *
 * Audit entries record who signed in, so a transient logging failure must be
 * retried rather than silently dropped, and a terminal failure has to leave a
 * trace of its own.
 */
class AuthAuditFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_listener_declares_a_retry_policy(): void
    {
        $listener = new LogAuthActivity;

        $this->assertSame(3, $listener->tries());
        $this->assertSame([10, 60], $listener->backoff());
    }

    /**
     * The policy is read from config rather than hard coded, so the same listener
     * can be tuned per environment.
     */
    public function test_the_retry_policy_follows_the_configuration(): void
    {
        config()->set('queue.audit_retry.tries', 7);
        config()->set('queue.audit_retry.backoff', '5,30,90');

        $listener = new LogAuthActivity;

        $this->assertSame(7, $listener->tries());
        $this->assertSame([5, 30, 90], $listener->backoff());
    }

    public function test_the_retry_policy_reaches_the_queued_job(): void
    {
        Bus::fake();

        // Must be a persisted user: the event uses SerializesModels, so an
        // unsaved `make()` model has no key for the queued job to re-fetch
        // and dispatch() throws ModelNotFoundException.
        UserLoggedIn::dispatch(User::factory()->create(), 'token-id', new RequestContext(null, null));

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

        $event = new UserRegistered(
            user: $user,
            requestContext: new RequestContext('198.51.100.7', 'Audit Agent'),
        );

        (new CallQueuedListener(LogAuthActivity::class, 'handle', [$event]))
            ->failed(new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);

        $failure = $entries[0];

        $this->assertSame('Auth audit logging failed.', $failure->message);
        $this->assertSame('error', $failure->level);
        $this->assertSame('registered', $failure->context['event_type']);
        $this->assertSame($user->getKey(), $failure->context['user_id']);
        $this->assertSame('198.51.100.7', $failure->context['ip_address']);
        $this->assertSame(RuntimeException::class, $failure->context['exception']);
        $this->assertSame('log sink unreachable', $failure->context['error']);
    }

    /**
     * The failure report only needs the request origin, so it must read the
     * dedicated accessor. Calling context() would rebuild the whole audit
     * payload just to throw it away.
     */
    public function test_the_failure_report_does_not_build_the_whole_event_context(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $event = Mockery::mock(RecordsAuthActivity::class);
        $event->shouldReceive('eventType')->andReturn(AuthEventType::Registered);
        $event->shouldReceive('user')->andReturn(User::factory()->create());
        $event->shouldReceive('ipAddress')->andReturn('198.51.100.7');
        $event->shouldReceive('context')->never();

        (new LogAuthActivity)->failed($event, new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);
        $this->assertSame('198.51.100.7', $entries[0]->context['ip_address']);
    }

    /**
     * The regression this guards. LOG_AUTH_CHANNEL unset leaves
     * logging.auth_channel null, which Laravel resolves to logging.default, and
     * failed() used to write to logging.default as well. One broken sink then
     * took down both the audit entry and the report that it had failed.
     */
    public function test_the_failure_is_not_reported_on_the_channel_that_just_failed(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.auth_channel', null);
        config()->set('logging.auth_failure_channel', 'stderr');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        (new LogAuthActivity)->failed(
            new UserRegistered(user: User::factory()->create(), requestContext: new RequestContext('198.51.100.7', null)),
            new RuntimeException('log sink unreachable'),
        );
    }

    public function test_the_failure_channel_follows_the_configuration(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.auth_channel', 'daily');
        config()->set('logging.auth_failure_channel', 'syslog');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('syslog')->andReturn($logger);

        (new LogAuthActivity)->failed(
            new UserRegistered(user: User::factory()->create(), requestContext: new RequestContext('198.51.100.7', null)),
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
        config()->set('logging.auth_channel', 'daily');
        config()->set('logging.auth_failure_channel', 'daily');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        (new LogAuthActivity)->failed(
            new UserRegistered(user: User::factory()->create(), requestContext: new RequestContext('198.51.100.7', null)),
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
        config()->set('logging.auth_channel', null);
        config()->set('logging.auth_failure_channel', 'stderr');

        Log::shouldReceive('channel')->once()->with('stderr')->andThrow(new RuntimeException('stderr down'));

        (new LogAuthActivity)->failed(
            new UserRegistered(user: User::factory()->create(), requestContext: new RequestContext('198.51.100.7', null)),
            new RuntimeException('log sink unreachable'),
        );

        $this->assertTrue(true);
    }
}
