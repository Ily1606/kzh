<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Comment\CommentCreated;
use App\Listeners\Comment\LogCommentCreation;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The comment creation event, as the create endpoint actually produces it.
 *
 * Three things matter here: the event carries the snapshot the audit entry is
 * written from, a rejected request dispatches nothing, and the listener lands on
 * the queue. Everything the event and the listener do with that snapshot once
 * queued is covered by CommentAuditFailureHandlingTest.
 */
class CommentEventTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'comment_count' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['content' => 'This is a test comment.'], $overrides);
    }

    public function test_create_dispatches_comment_created_with_the_audit_snapshot(): void
    {
        Event::fake([CommentCreated::class]);

        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();

        Sanctum::actingAs($user);

        $this->withHeader('User-Agent', 'Comment Review Agent')
            ->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        Event::assertDispatched(
            CommentCreated::class,
            function (CommentCreated $event) use ($user, $plugin): bool {
                return $event->snapshot['comment_id'] !== null
                    && $event->snapshot['author_id'] === $user->id
                    && $event->snapshot['plugin_id'] === $plugin->id
                    && $event->snapshot['parent_comment_id'] === null
                    && $event->context()['comment_id'] === $event->snapshot['comment_id']
                    && $event->context()['ip_address'] === '127.0.0.1'
                    && $event->context()['user_agent'] === 'Comment Review Agent';
            },
        );
    }

    public function test_an_invalid_request_dispatches_no_comment_created_event(): void
    {
        Event::fake([CommentCreated::class]);
        Sanctum::actingAs(User::factory()->create());

        $plugin = $this->approvedPlugin();

        // Rejected by validation, so nothing is written and nothing is announced.
        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", [])
            ->assertUnprocessable();

        Event::assertNotDispatched(CommentCreated::class);
    }

    public function test_comment_creation_listener_is_queued_once(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $plugin = $this->approvedPlugin();

        $this->withToken($token)
            ->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogCommentCreation::class, $job->class);
        $this->assertSame('handle', $job->method);
    }
}
