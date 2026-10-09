<?php

namespace Tests\Feature;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The reviewer's half of the timeline.
 *
 * Every admin action writes exactly one event, and the plugin write and the
 * event are committed together. The rejection test is a regression: the modal
 * used to collect a reason and discard it, so an owner whose submission was
 * turned down was never told why.
 */
class AdminPluginEventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    private function pending(): Plugin
    {
        return Plugin::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);
    }

    public function test_approving_records_an_approved_event(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::Approved, $event->event_type);
        $this->assertSame($this->admin->id, $event->admin_id);
    }

    /**
     * An approval needs no explanation, so it carries none — but the event
     * still has to exist, because "it was approved" is the single fact an
     * owner most wants to read off their timeline.
     */
    public function test_an_approval_carries_no_message(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $this->assertNull(PluginEvent::where('plugin_id', $plugin->id)->sole()->message);
    }

    public function test_requesting_changes_records_an_update_requested_event(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('requestChanges', $plugin, [
            'message' => 'Please add a configuration example to the README.',
        ]);

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::UpdateRequested, $event->event_type);
        $this->assertSame('Please add a configuration example to the README.', $event->message);
        $this->assertSame($this->admin->id, $event->admin_id);
    }

    /**
     * The decision this feature exists for. Requesting changes is not a
     * verdict: the plugin stays pending and the owner keeps the right to have
     * it reviewed, so neither `status` nor `approved_at` may move.
     */
    public function test_requesting_changes_leaves_the_plugin_pending(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('requestChanges', $plugin, [
            'message' => 'Please clarify the license.',
        ]);

        $fresh = $plugin->fresh();

        $this->assertSame(PluginStatus::Pending, $fresh->status);
        $this->assertNull($fresh->approved_at);
    }

    /**
     * A message the owner can act on. The form makes it required, so an
     * unexplained request would defeat the point of the action.
     */
    public function test_requesting_changes_requires_a_message(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)
            ->callTableAction('requestChanges', $plugin, ['message' => ''])
            ->assertHasTableActionErrors(['message']);

        $this->assertSame(0, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    /**
     * Regression. `rejected_reason` was collected by the modal and then
     * dropped: only the status was ever written, so the owner saw a rejected
     * plugin and nothing explaining it.
     */
    public function test_rejecting_records_the_reason_it_collected(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('reject', $plugin, [
            'rejected_reason' => 'Duplicate of an existing plugin.',
        ]);

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::Rejected, $event->event_type);
        $this->assertSame('Duplicate of an existing plugin.', $event->message);
        $this->assertSame($this->admin->id, $event->admin_id);
    }

    /**
     * A rejection without a stated reason is a dead end for the owner: they
     * can resubmit, but not know what to change.
     */
    public function test_rejecting_requires_a_reason(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)
            ->callTableAction('reject', $plugin, ['rejected_reason' => ''])
            ->assertHasTableActionErrors(['rejected_reason']);

        $this->assertSame(0, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    /**
     * The owner's row and the reviewer's row must not be separable by asking
     * twice: one reviewer action is one event, and repeating it is a second
     * deliberate action that should be visible as such.
     */
    public function test_each_reviewer_action_adds_exactly_one_event(): void
    {
        $plugin = $this->pending();

        $page = Livewire::test(ListPlugins::class);

        $page->callTableAction('requestChanges', $plugin, ['message' => 'First note.']);
        $page->callTableAction('requestChanges', $plugin, ['message' => 'Second note.']);

        $this->assertSame(2, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    /**
     * An approved plugin is settled. Offering "request changes" there would
     * imply the approval could be walked back, which nothing in the data model
     * does — `approved_at` still gates every public query, so the plugin would
     * keep its place in the catalogue while a reviewer asked for edits nobody
     * had decided to withdraw.
     */
    public function test_requesting_changes_is_hidden_on_an_approved_plugin(): void
    {
        $approved = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Livewire::test(ListPlugins::class)
            ->assertTableActionHidden('requestChanges', $approved)
            ->assertTableActionHidden('approve', $approved)
            ->assertTableActionHidden('reject', $approved);
    }

    public function test_the_three_actions_are_offered_on_a_pending_plugin(): void
    {
        $pending = $this->pending();

        Livewire::test(ListPlugins::class)
            ->assertTableActionVisible('approve', $pending)
            ->assertTableActionVisible('reject', $pending)
            ->assertTableActionVisible('requestChanges', $pending);
    }

    /**
     * The plugin write and the event are one transaction. A reviewer left with
     * an approved plugin and a silent history would be the worst of both
     * worlds, so the two are asserted together.
     */
    public function test_the_status_change_and_its_event_are_both_persisted(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $fresh = $plugin->fresh();

        $this->assertSame(PluginStatus::Approved, $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertSame(1, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    /**
     * The timeline must survive the reviewer who wrote it. Deleting an admin
     * account would otherwise erase a plugin's entire review history.
     */
    public function test_a_deleted_admin_account_leaves_the_timeline_intact(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('requestChanges', $plugin, [
            'message' => 'Please document the config option.',
        ]);

        $this->admin->forceDelete();

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertNull($event->admin_id);
        $this->assertSame('Please document the config option.', $event->message);
    }
}
