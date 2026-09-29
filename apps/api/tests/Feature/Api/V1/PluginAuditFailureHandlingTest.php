<?php

namespace Tests\Feature\Api\V1;

use App\Events\Plugin\PluginSubmitted;
use App\Listeners\Plugin\LogPluginSubmission;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
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

    public function test_the_retry_policy_reaches_the_queued_job(): void
    {
        Bus::fake();

        PluginSubmitted::dispatch(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
        );

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
    }

    public function test_the_failure_handler_takes_the_event_before_the_throwable(): void
    {
        $parameters = (new ReflectionMethod(new LogPluginSubmission, 'failed'))->getParameters();

        // CallQueuedListener calls failed(...$this->data, $e), so the event has to
        // come first or the job blows up while reporting its own failure.
        $this->assertCount(2, $parameters);
        $this->assertSame(PluginSubmitted::class, (string) $parameters[0]->getType());
        $this->assertSame(\Throwable::class, (string) $parameters[1]->getType());
    }

    public function test_an_exhausted_job_reports_the_failure_with_enough_context(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->getKey()]);

        (new LogPluginSubmission)->failed(
            new PluginSubmitted(
                plugin: $plugin,
                user: $user,
                ipAddress: '198.51.100.7',
                userAgent: 'Plugin Review Agent',
            ),
            new RuntimeException('log sink unreachable'),
        );

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
                ipAddress: '198.51.100.7',
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
}
