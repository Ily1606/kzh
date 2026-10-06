<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Events\Plugin\PluginUpdated;
use App\Listeners\Plugin\LogPluginUpdate;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginService;
use App\Support\RequestContext;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class PluginUpdateEventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pluginFor(User $user, array $overrides = []): Plugin
    {
        return Plugin::factory()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Audit Plugin',
            'title' => 'Audit Plugin Title',
            'license' => 'MIT',
            'source_link' => 'https://example.com/audit-plugin',
        ], $overrides));
    }

    public function test_update_dispatches_plugin_updated_with_the_editor_and_the_change(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Sau khi sửa',
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            function (PluginUpdated $event) use ($user, $plugin): bool {
                return $event->plugin->id === $plugin->id
                    && $event->user->is($user)
                    && $event->context()['plugin_id'] === $plugin->id
                    && $event->context()['user_id'] === $user->id
                    && $event->context()['name'] === 'Audit Plugin'
                    && $event->context()['ip_address'] === '127.0.0.1';
            },
        );
    }

    /**
     * The reason the snapshot exists at all: an entry saying a plugin was
     * renamed, without saying what it was renamed from, cannot answer the only
     * question an auditor asks of it.
     */
    public function test_the_change_carries_the_value_before_and_after_each_field(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Tên mới',
            'license' => 'GPL-3.0',
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => $event->changes === [
                'title' => ['from' => 'Audit Plugin Title', 'to' => 'Tên mới'],
                'license' => ['from' => 'MIT', 'to' => 'GPL-3.0'],
            ],
        );
    }

    /**
     * Only fields the edit actually moved. A field re-sent with the value it
     * already holds is not a change, and logging one would claim the plugin
     * moved when it did not.
     */
    public function test_only_the_fields_that_really_changed_are_reported(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Audit Plugin Title',
            'source_link' => 'https://example.com/moved',
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => array_keys($event->changes) === ['source_link']
                && $event->changes['source_link'] === [
                    'from' => 'https://example.com/audit-plugin',
                    'to' => 'https://example.com/moved',
                ],
        );
    }

    public function test_the_change_reports_no_field_the_client_could_not_edit(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, [
            'status' => PluginStatus::Approved,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Chỉ title đổi',
            'star_count' => 9999,
            'status' => PluginStatus::Rejected->value,
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => array_keys($event->changes) === ['title'],
        );
    }

    /**
     * update() ends in refresh(), so the pre-edit values are only readable
     * before the write. Diffing afterwards would report every field as
     * unchanged.
     */
    public function test_the_change_is_captured_before_the_write_overwrites_it(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'name' => 'Đổi tên',
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => $event->changes['name'] === [
                'from' => 'Audit Plugin',
                'to' => 'Đổi tên',
            ],
        );
    }

    public function test_the_event_captures_the_real_ip_and_user_agent_of_the_request(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('User-Agent', 'Plugin Edit Agent')
            ->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Edit'])
            ->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Plugin Edit Agent'
                && $event->context()['ip_address'] === '198.51.100.7'
                && $event->context()['user_agent'] === 'Plugin Edit Agent',
        );
    }

    /**
     * The queued listener runs in a worker, where the bound request is the
     * console request. The context snapshotted at construction time has to
     * survive the queue payload untouched.
     */
    public function test_event_context_survives_the_queue_round_trip(): void
    {
        $event = new PluginUpdated(
            plugin: Plugin::factory()->create(),
            user: User::factory()->create(),
            requestContext: new RequestContext('198.51.100.7', 'Plugin Edit Agent'),
            changes: ['title' => ['from' => 'A', 'to' => 'B']],
        );

        $roundTripped = unserialize(serialize($event));

        $this->assertSame('198.51.100.7', $roundTripped->requestContext->ipAddress);
        $this->assertSame('Plugin Edit Agent', $roundTripped->requestContext->userAgent);
        $this->assertSame('198.51.100.7', $roundTripped->context()['ip_address']);
        $this->assertSame(
            ['title' => ['from' => 'A', 'to' => 'B']],
            $roundTripped->changes,
        );
    }

    public function test_plugin_service_forwards_the_request_metadata_it_receives(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);

        $this->app->make(PluginService::class)->update(
            $user,
            $plugin->id,
            ['title' => 'Console edit'],
            new RequestContext('198.51.100.7', 'Console Runner'),
        );

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => $event->requestContext->ipAddress === '198.51.100.7'
                && $event->requestContext->userAgent === 'Console Runner',
        );
    }

    public function test_a_rejected_update_dispatches_no_event(): void
    {
        Event::fake([PluginUpdated::class]);

        $owner = User::factory()->create();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Của người khác'])
            ->assertForbidden();

        Event::assertNotDispatched(PluginUpdated::class);
    }

    public function test_an_invalid_update_dispatches_no_event(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['license' => 'Not-A-License'])
            ->assertUnprocessable();

        Event::assertNotDispatched(PluginUpdated::class);
    }

    public function test_a_failed_write_dispatches_no_event(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user, ['name' => 'Tên cũ']);
        Plugin::factory()->create([
            'user_id' => $user->id,
            'name' => 'Tên đã có',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['name' => 'Tên đã có'])
            ->assertUnprocessable();

        Event::assertNotDispatched(PluginUpdated::class);
    }

    /**
     * Re-sending the value a field already holds is a successful request with
     * nothing to record: the event still fires, but with no change to report.
     */
    public function test_a_no_op_edit_dispatches_the_event_with_an_empty_change_set(): void
    {
        Event::fake([PluginUpdated::class]);

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", [
            'title' => 'Audit Plugin Title',
        ])->assertOk();

        Event::assertDispatched(
            PluginUpdated::class,
            fn (PluginUpdated $event): bool => $event->changes === [],
        );
    }

    public function test_the_update_listener_is_queued_once(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Queued edit'])
            ->assertOk();

        Bus::assertDispatchedTimes(CallQueuedListener::class, 1);

        $job = Bus::dispatched(CallQueuedListener::class)->sole();

        $this->assertSame(LogPluginUpdate::class, $job->class);
        $this->assertSame('handle', $job->method);
    }

    public function test_the_update_is_logged_once_in_sync_test_environment(): void
    {
        $writes = 0;

        Log::listen(function () use (&$writes): void {
            $writes++;
        });

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Logged edit'])
            ->assertOk();

        $this->assertSame(1, $writes);
    }

    public function test_the_update_listener_declares_a_retry_policy(): void
    {
        $listener = new LogPluginUpdate;

        $this->assertSame(3, $listener->tries());
        $this->assertSame([10, 60], $listener->backoff());
    }

    /**
     * The failure reporter runs on the failure path, so it must read the frozen
     * snapshot rather than the model: re-resolving the row there could throw
     * and swallow the report it was asked to write.
     */
    public function test_the_failure_handler_reports_the_frozen_snapshot(): void
    {
        $entries = [];

        Log::listen(function ($message) use (&$entries): void {
            $entries[] = $message;
        });

        $user = User::factory()->create();
        $plugin = $this->pluginFor($user);

        (new CallQueuedListener(LogPluginUpdate::class, 'handle', [new PluginUpdated(
            plugin: $plugin,
            user: $user,
            requestContext: new RequestContext('198.51.100.7', null),
            changes: ['title' => ['from' => 'A', 'to' => 'B']],
        )]))
            ->failed(new RuntimeException('log sink unreachable'));

        $this->assertCount(1, $entries);

        $failure = $entries[0];

        $this->assertSame('Plugin update audit logging failed.', $failure->message);
        $this->assertSame('error', $failure->level);
        $this->assertSame($plugin->getKey(), $failure->context['plugin_id']);
        $this->assertSame($user->getKey(), $failure->context['user_id']);
        $this->assertSame('198.51.100.7', $failure->context['ip_address']);
        $this->assertSame(RuntimeException::class, $failure->context['exception']);
        $this->assertSame('log sink unreachable', $failure->context['error']);
    }

    /**
     * Same regression as the submission listener: logging.plugin_channel is null
     * when LOG_PLUGIN_CHANNEL is unset, so reporting the failure back into it
     * would raise the very exception being reported.
     */
    public function test_the_failure_is_not_reported_on_the_channel_that_just_failed(): void
    {
        config()->set('logging.default', 'stack');
        config()->set('logging.plugin_channel', null);
        config()->set('logging.plugin_failure_channel', 'stderr');

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once();

        Log::shouldReceive('channel')->once()->with('stderr')->andReturn($logger);

        $user = User::factory()->create();

        (new LogPluginUpdate)->failed(
            new PluginUpdated(
                plugin: $this->pluginFor($user),
                user: $user,
                requestContext: new RequestContext(null, null),
                changes: [],
            ),
            new RuntimeException('log sink unreachable'),
        );
    }
}
