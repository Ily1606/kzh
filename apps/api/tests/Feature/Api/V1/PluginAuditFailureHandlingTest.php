<?php

namespace Tests\Feature\Api\V1;

use App\Events\Plugin\PluginSubmitted;
use App\Listeners\Plugin\LogPluginSubmission;
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
 * Guards the retry policy of the plugin audit listener.
 *
 * A submission audit entry records who submitted what, so a transient logging
 * failure must be retried rather than silently dropped, and a terminal failure
 * has to leave a trace of its own.
 */
class PluginAuditFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_listener_declares_a_retry_policy(): void
    {
        $listener = new LogPluginSubmission;

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

        PluginSubmitted::dispatch(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
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
        $this->assertSame([5, 30], (new LogPluginSubmission)->backoff());

        config()->set('queue.audit_retry.backoff', '5, 30 ,90');
        $this->assertSame([5, 30, 90], (new LogPluginSubmission)->backoff());
    }

    /**
     * Same guard as the auth listener: `tries` of 0 means "retry forever" to
     * Laravel, so an empty or non-numeric env value would keep the submission
     * audit job alive for as long as the sink is down and never reach failed().
     */
    public function test_the_retry_policy_is_never_unlimited(): void
    {
        foreach ([0, '', 'not-a-number'] as $invalid) {
            config()->set('queue.audit_retry.tries', $invalid);

            $this->assertSame(1, (new LogPluginSubmission)->tries());
        }
    }

    public function test_the_retry_policy_reaches_the_queued_job(): void
    {
        Bus::fake();

        PluginSubmitted::dispatch(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
            requestContext: new RequestContext(null, null),
        );

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }

    /**
     * Driving the real `CallQueuedListener::failed()` is what keeps the argument
     * order honest: the worker calls `failed(...$event, $e)`, so a handler with
     * the parameters swapped raises a TypeError here instead of silently
     * reporting the wrong context to an operator.
     */
    public function test_an_exhausted_job_reports_the_failure_with_enough_context(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->getKey()]);

        $event = new PluginSubmitted(
            plugin: $plugin,
            user: $user,
            requestContext: new RequestContext('198.51.100.7', 'Plugin Review Agent'),
        );

        (new CallQueuedListener(LogPluginSubmission::class, 'handle', [$event]))
            ->failed(new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);

        $failure = $entries[0];

        $this->assertSame('Plugin audit logging failed.', $failure->message);
        $this->assertSame('error', $failure->level);
        $this->assertSame($plugin->getKey(), $failure->context['plugin_id']);
        $this->assertSame($user->getKey(), $failure->context['user_id']);
        $this->assertSame('198.51.100.7', $failure->context['ip_address']);
        $this->assertSame(RuntimeException::class, $failure->context['exception']);
        $this->assertSame('log sink unreachable', $failure->context['error']);
    }

    /**
     * The reporter runs on the failure path, so it must not depend on model
     * attributes that the audit context depends on. Reading `context()` here would
     * cast `status` and blow up on a row that no longer resolves, turning a logged
     * failure into a silent one.
     */
    public function test_the_failure_handler_does_not_depend_on_model_attributes(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();

        // A row the worker could not re-resolve: no key, no `status` to cast.
        (new LogPluginSubmission)->failed(
            new PluginSubmitted(
                plugin: new Plugin,
                user: $user,
                requestContext: new RequestContext('198.51.100.7', null),
            ),
            new RuntimeException('log sink unreachable'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('Plugin audit logging failed.', $entries[0]->message);
        $this->assertNull($entries[0]->context['plugin_id']);
        $this->assertSame($user->getKey(), $entries[0]->context['user_id']);
        $this->assertSame('198.51.100.7', $entries[0]->context['ip_address']);
        $this->assertSame('log sink unreachable', $entries[0]->context['error']);
    }

    /**
     * The same regression as the auth listener: logging.plugin_channel is null
     * whenever LOG_PLUGIN_CHANNEL is unset, and Laravel resolves that null to
     * logging.default, so one broken sink took down both the audit entry and
     * the report that it had failed.
     */
    public function test_the_failure_is_not_reported_on_the_channel_that_just_failed(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.plugin_channel', null);
        config()->set('logging.plugin_failure_channel', 'stderr');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        (new LogPluginSubmission)->failed(
            new PluginSubmitted(
                plugin: Plugin::factory()->create(['user_id' => User::factory()->create()->getKey()]),
                user: User::factory()->create(),
                requestContext: new RequestContext(null, null),
            ),
            new RuntimeException('log sink unreachable'),
        );
    }
}
