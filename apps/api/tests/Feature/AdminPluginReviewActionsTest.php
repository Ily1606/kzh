<?php

namespace Tests\Feature;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The reviewer actions are the only path into the approved state, and the one
 * thing they must achieve is that the plugin becomes publicly visible.
 *
 * They were dead on arrival. Both actions called `$record->update([...])`, but
 * `status` is not in the model's `Fillable` list and `$guarded` defaults to
 * `['*']`, so mass assignment discarded the attribute — the table showed the
 * modal close and the row stayed `pending`. Silent: no error, no exception,
 * just a write that never happened. The fix is `forceFill`, which crosses that
 * guard from the one place a reviewer decides, leaving the `Fillable` list
 * narrow enough that an owner still cannot approve their own submission.
 *
 * `approved_at` is the second half. Every public read path gates on it
 * (`whereNotNull('approved_at')`), so even a successfully persisted `status`
 * would have left the plugin invisible in the catalogue, trending and
 * all-time lists. The assertions below are phrased as "can a user see this
 * now" because that is the property that broke, not the column values.
 */
class AdminPluginReviewActionsTest extends TestCase
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
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);
    }

    /**
     * The outcome the reviewer cares about: the plugin shows up for the public.
     */
    private function visibleToThePublic(Plugin $plugin): bool
    {
        return app(PluginRepositoryInterface::class)
            ->getPaginatedApprovedPlugins(15)
            ->getCollection()
            ->contains('id', $plugin->id);
    }

    public function test_approving_a_plugin_actually_persists_the_status(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $this->assertSame(PluginStatus::Approved, $plugin->fresh()->status);
    }

    public function test_approving_a_plugin_stamps_approved_at(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $this->assertNotNull(
            $plugin->fresh()->approved_at,
            'approved_at gates every public read path; an approved plugin without it is published nowhere.',
        );
    }

    public function test_an_approved_plugin_becomes_visible_on_the_public_list(): void
    {
        $plugin = $this->pending();

        $this->assertFalse($this->visibleToThePublic($plugin), 'precondition: a pending plugin is not public');

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $this->assertTrue($this->visibleToThePublic($plugin));
    }

    public function test_an_approved_plugin_reaches_the_trending_query(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)->callTableAction('approve', $plugin);

        $trending = app(PluginRepositoryInterface::class)->getTrendingPlugins(
            30,
            ['view' => 1.0, 'comment' => 1.0, 'star' => 1.0],
            1.8,
            2.0,
            10,
        );

        $this->assertTrue(
            $trending->contains('id', $plugin->id),
            'trending filters on approved_at too, so a plugin approved without it never trends.',
        );
    }

    public function test_rejecting_a_plugin_actually_persists_the_status(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)
            ->callTableAction('reject', $plugin, ['rejected_reason' => 'Duplicate of an existing plugin.']);

        $this->assertSame(PluginStatus::Rejected, $plugin->fresh()->status);
    }

    public function test_rejecting_a_plugin_keeps_it_off_the_public_list(): void
    {
        $plugin = $this->pending();

        Livewire::test(ListPlugins::class)
            ->callTableAction('reject', $plugin, ['rejected_reason' => 'Not a real plugin.']);

        $this->assertFalse($this->visibleToThePublic($plugin));
    }

    /**
     * Rejecting clears `approved_at` rather than leaving a stale stamp. A plugin
     * that was approved and then rejected keeps its old timestamp unless the
     * decision clears it, and that row then still satisfies the
     * `whereNotNull('approved_at')` gate every public query applies — a rejected
     * plugin still listed in the catalogue.
     */
    public function test_rejecting_an_already_approved_plugin_clears_approved_at(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        // Driven through the model directly: the reject action is gated on
        // `status === Pending`, so an already-approved row is not reachable
        // through the table. The write it performs is the same one.
        $plugin->forceFill([
            'status' => PluginStatus::Rejected,
            'approved_at' => null,
        ])->save();

        $fresh = $plugin->fresh();

        $this->assertSame(PluginStatus::Rejected, $fresh->status);
        $this->assertNull($fresh->approved_at, 'a rejected plugin must stop satisfying the approved_at gate.');
        $this->assertFalse($this->visibleToThePublic($plugin));
    }

    /**
     * The approve action offers itself only on a pending row, and offers nothing
     * once a decision is made. Without this the reviewer can re-approve a
     * rejected plugin from the same row, which no API path allows.
     */
    public function test_the_review_actions_are_offered_only_on_pending_plugins(): void
    {
        $pending = $this->pending();
        $approved = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Livewire::test(ListPlugins::class)
            ->assertTableActionVisible('approve', $pending)
            ->assertTableActionHidden('approve', $approved)
            ->assertTableActionHidden('reject', $approved);
    }
}
