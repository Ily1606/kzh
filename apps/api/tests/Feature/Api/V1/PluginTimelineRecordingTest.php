<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The owner's half of the timeline: a submission and every revision they send.
 *
 * Both rows are written inside the transaction that persists the plugin, so
 * these tests assert on the database rather than on a dispatched event. The
 * timeline is the product here — an event class nothing listens to would leave
 * the owner looking at an empty history while every other test still passed.
 */
class PluginTimelineRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create();
    }

    private function pluginFor(User $owner, array $overrides = []): Plugin
    {
        return Plugin::factory()->create(array_merge([
            'user_id' => $owner->id,
            'name' => '@dsh/timeline-plugin',
            'title' => 'Timeline Plugin',
            'license' => 'MIT',
            'source_link' => 'https://example.com/timeline-plugin',
        ], $overrides));
    }

    public function test_submitting_a_plugin_records_a_created_event(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $pluginId = $this->postJson('/api/v1/plugins', [
            'name' => '@dsh/brand-new',
            'title' => 'Brand New',
            'license' => 'MIT',
            'source_link' => 'https://example.com/brand-new',
        ])->assertCreated()->json('data.plugin.id');

        $event = PluginEvent::where('plugin_id', $pluginId)->sole();

        $this->assertSame(PluginEventType::Created, $event->event_type);
    }

    /**
     * The owner's own action: there is no admin to attribute it to, and no
     * admin text to carry. A `created` row pointing at a reviewer would be a
     * lie on the owner's timeline.
     */
    public function test_a_created_event_names_no_admin_and_carries_no_message(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $pluginId = $this->postJson('/api/v1/plugins', [
            'name' => '@dsh/no-actor',
            'title' => 'No Actor',
            'license' => 'MIT',
            'source_link' => 'https://example.com/no-actor',
        ])->assertCreated()->json('data.plugin.id');

        $event = PluginEvent::where('plugin_id', $pluginId)->sole();

        $this->assertNull($event->admin_id);
        $this->assertNull($event->message);
    }

    /**
     * A submission refused on the unique name must leave no trace. Otherwise the
     * timeline accumulates rows for plugins that were never created, and the
     * owner reads history for a submission that does not exist.
     */
    public function test_a_refused_submission_records_nothing(): void
    {
        $owner = $this->owner();
        $this->pluginFor($owner, ['name' => '@dsh/taken']);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/plugins', [
            'name' => '@dsh/taken',
            'title' => 'Duplicate',
            'license' => 'MIT',
            'source_link' => 'https://example.com/duplicate',
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(0, PluginEvent::count());
    }

    public function test_editing_a_plugin_records_a_resubmitted_event(): void
    {
        $owner = $this->owner();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Sau khi sửa'])
            ->assertOk();

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::Resubmitted, $event->event_type);
        $this->assertNull($event->admin_id);
        $this->assertNull($event->message);
    }

    /**
     * The rule that makes `update_requested` meaningful: the plugin stays
     * pending throughout. A reviewer asking for changes is not a decision, and
     * turning it into one would withdraw a submission the owner is still
     * entitled to have reviewed.
     */
    public function test_resubmitting_leaves_the_status_untouched(): void
    {
        $owner = $this->owner();
        $plugin = $this->pluginFor($owner, [
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Sửa xong'])
            ->assertOk();

        $fresh = $plugin->fresh();

        $this->assertSame(PluginStatus::Pending, $fresh->status);
        $this->assertNull($fresh->approved_at);
    }

    /**
     * An owner may revise as often as they like without waiting on a reviewer,
     * and every revision is a real step in the history.
     */
    public function test_repeated_edits_accumulate_one_event_each(): void
    {
        $owner = $this->owner();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'V1'])->assertOk();
        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'V2'])->assertOk();
        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'V3'])->assertOk();

        $this->assertSame(3, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    /**
     * The save and its timeline row are one unit of work. Splitting them would
     * let a failure leave the owner a recorded resubmission for a revision that
     * was never saved.
     */
    public function test_a_failed_edit_records_no_resubmission(): void
    {
        $owner = $this->owner();
        $plugin = $this->pluginFor($owner);
        Sanctum::actingAs($owner);

        // Renaming onto a name this owner already holds fails on the unique
        // index, after the timeline row would otherwise have been written.
        $this->pluginFor($owner, ['name' => '@dsh/occupied']);

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['name' => '@dsh/occupied'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(0, PluginEvent::where('plugin_id', $plugin->id)->count());
    }

    public function test_editing_someone_elses_plugin_records_nothing(): void
    {
        $plugin = $this->pluginFor($this->owner());
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/plugins/{$plugin->id}", ['title' => 'Cướp'])
            ->assertForbidden();

        $this->assertSame(0, PluginEvent::where('plugin_id', $plugin->id)->count());
    }
}
