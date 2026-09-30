<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Comment\CommentCreated;
use App\Listeners\Comment\LogCommentCreation;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Services\CommentService;
use App\Support\RequestContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['content' => 'This is a test comment.'], $overrides);
    }

    public function test_create_dispatches_comment_created_event_with_comment_author_and_context(): void
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
                return $event->comment->author_id === $user->id
                    && $event->author->is($user)
                    && $event->comment->plugin_id === $plugin->id
                    && $event->context()['comment_id'] === $event->comment->id
                    && $event->context()['plugin_id'] === $plugin->id
                    && $event->context()['author_id'] === $user->id
                    && $event->context()['parent_comment_id'] === null
                    && $event->context()['ip_address'] === '127.0.0.1'
                    && $event->context()['user_agent'] === 'Comment Review Agent';
            },
        );
    }

    public function test_reply_event_carries_the_parent_comment_id(): void
    {
        Event::fake([CommentCreated::class]);

        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $parent->id,
        ]))->assertCreated();

        Event::assertDispatched(
            CommentCreated::class,
            fn (CommentCreated $event): bool => $event->comment->parent_comment_id === $parent->id
                && $event->context()['parent_comment_id'] === $parent->id,
        );
    }

    public function test_event_captures_the_real_ip_of_the_request(): void
    {
        Event::fake([CommentCreated::class]);

        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('User-Agent', 'Comment Review Agent')
            ->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        Event::assertDispatched(
            CommentCreated::class,
            fn (CommentCreated $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Comment Review Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Comment Review Agent',
        );
    }

    /**
     * The queued listener runs in a worker, where the bound request is the
     * console request (127.0.0.1 / "Symfony"). The context snapshotted at
     * construction time has to survive the queue payload untouched and must
     * never be rebuilt from the request the worker happens to see.
     */
    public function test_event_context_stays_the_origin_request_after_the_queue_round_trip(): void
    {
        $event = new CommentCreated(
            comment: Comment::factory()->create(),
            author: User::factory()->create(),
            requestContext: new RequestContext('198.51.100.7', 'Comment Review Agent'),
        );

        // A console request answers 127.0.0.1 / "Symfony"; binding it makes the
        // fallback this test guards against observable.
        $consoleRequest = Request::create('/');
        $this->app->instance('request', $consoleRequest);

        $this->assertSame('127.0.0.1', $consoleRequest->ip());
        $this->assertSame('Symfony', $consoleRequest->userAgent());

        // Round-trip through the queue payload, exactly what CallQueuedListener
        // does before it invokes the listener.
        $workerSideEvent = unserialize(serialize($event));

        $this->assertSame('198.51.100.7', $workerSideEvent->requestContext->ipAddress);
        $this->assertSame('Comment Review Agent', $workerSideEvent->requestContext->userAgent);
        $this->assertSame('198.51.100.7', $workerSideEvent->context()['ip_address']);
        $this->assertSame('Comment Review Agent', $workerSideEvent->context()['user_agent']);
    }

    /**
     * Only the controller may read the HTTP request. The service must forward the
     * metadata it is given rather than resolving a request of its own, so it stays
     * usable from a console command or another non-HTTP caller.
     */
    public function test_comment_service_forwards_the_request_metadata_it_receives(): void
    {
        Event::fake([CommentCreated::class]);

        $this->app->make(CommentService::class)->create(
            User::factory()->create(),
            $this->approvedPlugin()->id,
            $this->payload(),
            new RequestContext('198.51.100.7', 'Console Runner'),
        );

        Event::assertDispatched(
            CommentCreated::class,
            fn (CommentCreated $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Console Runner',
        );
    }

    /**
     * A non-HTTP caller must opt in to missing metadata explicitly. The context
     * itself is still required so a forgotten argument fails fast instead of
     * silently dropping the audit metadata.
     */
    public function test_comment_service_needs_no_http_request_to_dispatch_events(): void
    {
        Event::fake([CommentCreated::class]);

        // Simulate a non-HTTP caller: the service must not depend on a request.
        $this->app->instance('request', Request::create('/'));

        $this->app->make(CommentService::class)->create(
            User::factory()->create(),
            $this->approvedPlugin()->id,
            $this->payload(),
            new RequestContext(null, null),
        );

        Event::assertDispatched(
            CommentCreated::class,
            fn (CommentCreated $event): bool => $event->requestContext->ipAddress === null
                && $event->requestContext->userAgent === null,
        );
    }

    public function test_invalid_comment_does_not_dispatch_comment_created_event(): void
    {
        Event::fake([CommentCreated::class]);
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", [])
            ->assertUnprocessable();

        Event::assertNotDispatched(CommentCreated::class);
    }

    public function test_comment_on_an_unapproved_plugin_does_not_dispatch_the_event(): void
    {
        Event::fake([CommentCreated::class]);
        Sanctum::actingAs(User::factory()->create());

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertNotFound();

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

    public function test_comment_creation_is_logged_once_in_sync_test_environment(): void
    {
        $writes = 0;

        Log::listen(function () use (&$writes): void {
            $writes++;
        });

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $plugin = $this->approvedPlugin();

        $this->withToken($token)
            ->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        $this->assertSame(1, $writes);
    }
}
