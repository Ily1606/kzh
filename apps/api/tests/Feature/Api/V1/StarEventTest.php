<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Star\PluginStarred;
use App\Events\Star\PluginUnstarred;
use App\Listeners\Star\LogStarChange;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The star events, as the endpoint actually produces them.
 *
 * Three things matter: the event carries the snapshot the audit entry is written
 * from, a request that changes nothing announces nothing, and the listener lands
 * on the queue. What the listener does with that snapshot once queued is covered
 * by StarAuditFailureHandlingTest.
 */
class StarEventTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    private function star(Plugin $plugin, bool $starred): TestResponse
    {
        return $this->postJson("/api/v1/plugins/{$plugin->id}/star", ['starred' => $starred]);
    }

    public function test_starring_dispatches_plugin_starred_with_the_audit_snapshot(): void
    {
        Event::fake([PluginStarred::class, PluginUnstarred::class]);

        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        Sanctum::actingAs($user);

        $this->withHeader('User-Agent', 'Star Review Agent')
            ->postJson("/api/v1/plugins/{$plugin->id}/star", ['starred' => true])
            ->assertOk();

        Event::assertDispatched(
            PluginStarred::class,
            function (PluginStarred $event) use ($user, $plugin): bool {
                return $event->snapshot['plugin_id'] === $plugin->id
                    && $event->snapshot['user_id'] === $user->id
                    && $event->context()['plugin_id'] === $event->snapshot['plugin_id']
                    && $event->context()['ip_address'] === '127.0.0.1'
                    && $event->context()['user_agent'] === 'Star Review Agent';
            },
        );
    }

    public function test_unstarring_dispatches_plugin_unstarred(): void
    {
        Event::fake([PluginStarred::class, PluginUnstarred::class]);

        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        Sanctum::actingAs($user);

        $this->star($plugin, true)->assertOk();
        $this->star($plugin, false)->assertOk();

        Event::assertDispatched(
            PluginUnstarred::class,
            function (PluginUnstarred $event) use ($user, $plugin): bool {
                return $event->snapshot['plugin_id'] === $plugin->id
                    && $event->snapshot['user_id'] === $user->id;
            },
        );
    }

    /**
     * The endpoint is set-state, so a repeated `starred: true` writes nothing.
     * Announcing it anyway would make the audit log claim more stars than the
     * table holds — the log would stop being a record of what happened.
     */
    public function test_starring_an_already_starred_plugin_dispatches_nothing(): void
    {
        Event::fake([PluginStarred::class, PluginUnstarred::class]);

        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        Sanctum::actingAs($user);

        $this->star($plugin, true)->assertOk();
        Event::fake([PluginStarred::class, PluginUnstarred::class]);

        $this->star($plugin, true)->assertOk();

        Event::assertNotDispatched(PluginStarred::class);
        Event::assertNotDispatched(PluginUnstarred::class);
    }

    public function test_unstarring_a_plugin_that_was_not_starred_dispatches_nothing(): void
    {
        Event::fake([PluginStarred::class, PluginUnstarred::class]);

        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        Sanctum::actingAs($user);

        $this->star($plugin, false)->assertOk();

        Event::assertNotDispatched(PluginUnstarred::class);
    }

    public function test_an_invalid_request_dispatches_no_star_event(): void
    {
        Event::fake([PluginStarred::class, PluginUnstarred::class]);
        Sanctum::actingAs(User::factory()->create());

        $plugin = $this->approvedPlugin();

        // Rejected by validation, so nothing is written and nothing is announced.
        $this->postJson("/api/v1/plugins/{$plugin->id}/star", [])->assertUnprocessable();

        Event::assertNotDispatched(PluginStarred::class);
        Event::assertNotDispatched(PluginUnstarred::class);
    }

    public function test_the_star_listener_is_queued_once_per_change(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $plugin = $this->approvedPlugin();

        $this->withToken($token)
            ->postJson("/api/v1/plugins/{$plugin->id}/star", ['starred' => true])
            ->assertOk();

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogStarChange::class, $job->class);
        $this->assertSame('handle', $job->method);
    }
}
