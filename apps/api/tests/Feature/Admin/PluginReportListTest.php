<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Plugins\PluginReportResource\Pages\ListPluginReports;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PluginReportListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    public function test_admin_can_view_plugin_reports_list(): void
    {
        $reporter = User::factory()->create(['name' => 'John Reporter']);
        $plugin = Plugin::factory()->create(['name' => 'suspicious-plugin']);

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'This plugin contains questionable code.',
        ]);

        Livewire::test(ListPluginReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertSee('John Reporter')
            ->assertSee('suspicious-plugin');
    }

    public function test_admin_can_dismiss_plugin_report(): void
    {
        $reporter = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Spam plugin submission.',
        ]);

        Livewire::test(ListPluginReports::class)
            ->callTableAction('delete', $report);

        $this->assertTrue($report->fresh()->trashed());
    }

    public function test_admin_can_restore_dismissed_plugin_report(): void
    {
        $reporter = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Spam plugin submission.',
        ]);
        $report->delete();

        $this->assertTrue($report->fresh()->trashed());

        Livewire::test(ListPluginReports::class)
            ->callTableAction('restore', $report);

        $this->assertFalse($report->fresh()->trashed());
    }

    public function test_admin_can_filter_trashed_reports(): void
    {
        $reporter1 = User::factory()->create();
        $reporter2 = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $activeReport = PluginReport::create([
            'user_id' => $reporter1->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Active report reason here.',
        ]);

        $dismissedReport = PluginReport::create([
            'user_id' => $reporter2->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Dismissed report reason here.',
        ]);
        $dismissedReport->delete();

        // Default: active reports visible, trashed hidden
        Livewire::test(ListPluginReports::class)
            ->assertCanSeeTableRecords([$activeReport])
            ->assertCanNotSeeTableRecords([$dismissedReport]);
    }
}
