<?php

namespace Tests\Feature;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\User;
use App\Services\PluginEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The reviewer half of the timeline, exercised through the service rather than
 * through Filament.
 *
 * This is the case for the pairing living in the service: the transaction that
 * keeps a decision and its plugin change together is a property of the
 * operation, not of the screen that triggers it. Driving it from here covers a
 * second route into review — a command, a queue job, an API endpoint — which
 * would otherwise each have to remember the wrapping for itself.
 */
class PluginEventReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PluginEventService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->service = app(PluginEventService::class);
    }

    private function pending(): Plugin
    {
        return Plugin::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);
    }

    public function test_review_records_the_decision_and_applies_the_change(): void
    {
        $plugin = $this->pending();

        $this->service->review(
            $plugin,
            PluginEventType::Approved,
            $this->admin,
            write: fn (Plugin $p) => $p->forceFill([
                'status' => PluginStatus::Approved,
                'approved_at' => now(),
            ])->save(),
        );

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::Approved, $event->event_type);
        $this->assertSame($this->admin->id, $event->admin_id);
        $this->assertSame(PluginStatus::Approved, $plugin->fresh()->status);
        $this->assertNotNull($plugin->fresh()->approved_at);
    }

    /**
     * The guarantee this method exists for. A plugin whose row says `approved`
     * while its history never mentions it tells the owner the opposite of what
     * happened, and there is no way for them to discover the discrepancy.
     */
    public function test_a_failure_in_the_plugin_change_rolls_back_the_event(): void
    {
        $plugin = $this->pending();

        try {
            $this->service->review(
                $plugin,
                PluginEventType::Approved,
                $this->admin,
                write: function (Plugin $p): void {
                    throw new \RuntimeException('The write failed halfway.');
                },
            );
        } catch (\RuntimeException) {
            // Expected: the point is what survived, not that it threw.
        }

        $this->assertSame(0, PluginEvent::where('plugin_id', $plugin->id)->count());
        $this->assertSame(PluginStatus::Pending, $plugin->fresh()->status);
    }

    /**
     * `update_requested` writes no `write`, because it changes nothing about
     * the plugin. It is still recorded, and the status is still untouched.
     */
    public function test_a_decision_without_a_write_leaves_the_plugin_alone(): void
    {
        $plugin = $this->pending();

        $this->service->review(
            $plugin,
            PluginEventType::UpdateRequested,
            $this->admin,
            'Please document the config option.',
        );

        $event = PluginEvent::where('plugin_id', $plugin->id)->sole();

        $this->assertSame(PluginEventType::UpdateRequested, $event->event_type);
        $this->assertSame('Please document the config option.', $event->message);
        $this->assertSame(PluginStatus::Pending, $plugin->fresh()->status);
    }

    /**
     * Both halves run inside a transaction, so a decision cannot land while the
     * plugin change is still open.
     */
    public function test_the_plugin_change_and_the_event_share_one_transaction(): void
    {
        $plugin = $this->pending();

        $openAtWrite = null;

        $this->service->review(
            $plugin,
            PluginEventType::Rejected,
            $this->admin,
            'Not a real plugin.',
            function (Plugin $p) use (&$openAtWrite): void {
                $openAtWrite = DB::transactionLevel();

                $p->forceFill(['status' => PluginStatus::Rejected])->save();
            },
        );

        $this->assertGreaterThan(0, $openAtWrite, 'The plugin change must run inside a transaction.');
    }

    /**
     * Reviewing is not the owner's to do. The service takes the admin as an
     * argument rather than reading it from the container, so this is the
     * caller's responsibility — and it is stated in the signature rather than
     * left to whichever screen happens to invoke it.
     */
    public function test_the_event_records_the_admin_passed_in(): void
    {
        $plugin = $this->pending();
        $other = User::factory()->create(['is_admin' => true]);

        $this->service->review($plugin, PluginEventType::Approved, $other);

        $this->assertSame($other->id, PluginEvent::where('plugin_id', $plugin->id)->sole()->admin_id);
    }
}
