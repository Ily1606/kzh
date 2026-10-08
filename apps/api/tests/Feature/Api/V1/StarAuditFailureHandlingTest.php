<?php

namespace Tests\Feature\Api\V1;

use App\Events\Star\PluginStarred;
use App\Events\Star\PluginUnstarred;
use App\Listeners\Star\LogStarChange;
use App\Models\Plugin;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Star-specific guards for the audit listener.
 *
 * The retry policy and the audit/failure channel split both live in the shared
 * traits and are covered once per trait by AuthAuditFailureHandlingTest and
 * PluginAuditFailureHandlingTest. What is left here is what is specific to
 * stars: the identifiers the report carries, that both events are covered, and
 * that the report is still produced once the rows behind it are gone.
 */
class StarAuditFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captures everything written through the log facade for the rest of the
     * test.
     *
     * Bound by reference inside each test rather than in a helper: the closure
     * has to close over the *same* variable the assertions read, and an array
     * returned from a helper would be a copy, leaving the listener appending to
     * a local that no longer exists.
     *
     * @param  array<int, mixed>  $entries
     */
    private function listen(array &$entries): void
    {
        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });
    }

    public function test_the_failure_report_carries_the_star_audit_identifiers(): void
    {
        $entries = [];
        $this->listen($entries);

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $event = new PluginStarred(
            pluginId: $plugin->id,
            userId: $user->getKey(),
            requestContext: new RequestContext('198.51.100.7', 'Star Review Agent'),
        );

        (new CallQueuedListener(LogStarChange::class, 'handle', [$event]))
            ->failed(new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);

        $failure = $entries[0];

        $this->assertSame('Star audit logging failed.', $failure->message);
        $this->assertSame('error', $failure->level);
        $this->assertSame($plugin->id, $failure->context['plugin_id']);
        $this->assertSame($user->getKey(), $failure->context['user_id']);
        $this->assertSame('198.51.100.7', $failure->context['ip_address']);
        $this->assertSame(RuntimeException::class, $failure->context['exception']);
        $this->assertSame('log sink unreachable', $failure->context['error']);
    }

    /**
     * One listener serves both event types, so the failure path has to work for
     * the unstar too — an unhandled type here would silently drop the report.
     */
    public function test_the_failure_report_also_covers_unstarring(): void
    {
        $entries = [];
        $this->listen($entries);

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $event = new PluginUnstarred(
            pluginId: $plugin->id,
            userId: $user->getKey(),
            requestContext: new RequestContext('198.51.100.7', null),
        );

        (new LogStarChange)->failed(
            $event,
            new RuntimeException('log sink unreachable'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('Star audit logging failed.', $entries[0]->message);
        $this->assertSame($plugin->id, $entries[0]->context['plugin_id']);
    }

    /**
     * The reporter runs on the failure path, so it must not depend on any row
     * still being there. A star disappears when its plugin is hard-deleted — the
     * FK cascades — and the retry backoff is 10s/60s, so by the time a job is
     * finally declared failed both rows are routinely gone.
     *
     * Reading context() or a model here would throw on exactly that path,
     * turning a logged failure into a silent one. So the events carry
     * dispatch-time scalars and the report comes out complete.
     */
    public function test_the_failure_handler_reports_even_after_the_rows_are_gone(): void
    {
        $entries = [];
        $this->listen($entries);

        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $event = new PluginStarred(
            pluginId: $plugin->id,
            userId: $user->getKey(),
            requestContext: new RequestContext('198.51.100.7', null),
        );

        // Both rows are gone by the time the job runs out of attempts. Plugins
        // soft-delete, so it takes forceDelete() to actually remove the row and
        // let the FK cascade reach the stars.
        $plugin->forceDelete();
        $this->assertDatabaseMissing('plugins', ['id' => $plugin->id]);

        // Users soft-delete as well.
        $user->forceDelete();
        $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);

        (new LogStarChange)->failed(
            unserialize(serialize($event)),
            new RuntimeException('log sink unreachable'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('Star audit logging failed.', $entries[0]->message);
        $this->assertSame($plugin->id, $entries[0]->context['plugin_id']);
        $this->assertSame($user->getKey(), $entries[0]->context['user_id']);
        $this->assertSame('198.51.100.7', $entries[0]->context['ip_address']);
        $this->assertSame('log sink unreachable', $entries[0]->context['error']);
    }
}
