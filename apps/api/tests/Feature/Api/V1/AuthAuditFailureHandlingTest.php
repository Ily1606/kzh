<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\RecordsAuthActivity;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Listeners\Auth\LogAuthActivity;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
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

        UserLoggedIn::dispatch(User::factory()->make(), 'token-id');

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }

    public function test_the_failure_handler_takes_the_event_before_the_throwable(): void
    {
        $parameters = (new ReflectionMethod(new LogAuthActivity, 'failed'))->getParameters();

        // CallQueuedListener calls failed(...$this->data, $e), so the event has to
        // come first or the job blows up while reporting its own failure.
        $this->assertCount(2, $parameters);
        $this->assertSame(RecordsAuthActivity::class, (string) $parameters[0]->getType());
        $this->assertSame(\Throwable::class, (string) $parameters[1]->getType());
    }

    public function test_an_exhausted_job_reports_the_failure_with_enough_context(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();

        (new LogAuthActivity)->failed(
            new UserRegistered(
                user: $user,
                ipAddress: '198.51.100.7',
                userAgent: 'Audit Agent',
            ),
            new RuntimeException('log sink unreachable'),
        );

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
}
