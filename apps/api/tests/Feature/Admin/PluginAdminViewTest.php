<?php

namespace Tests\Feature\Admin;

use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Filament\Resources\Plugins\Pages\ViewPlugin;
use App\Filament\Resources\Plugins\PluginReportResource\Pages\ViewPluginReport;
use App\Filament\Resources\Plugins\PluginResource;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PluginAdminViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    public function test_admin_can_view_plugin_details_page(): void
    {
        $author = User::factory()->create(['name' => 'Plugin Creator', 'email' => 'creator@example.com']);
        $category = Category::create(['name' => 'DevOps']);
        $tag = Tag::create(['name' => 'cloud']);

        $plugin = Plugin::factory()->create([
            'user_id' => $author->id,
            'name' => 'cloud-deployer-tool',
            'title' => 'Cloud Deployer Tool',
            'license' => 'MIT',
            'source_link' => 'https://github.com/example/cloud-deployer',
            'view_count' => 150,
        ]);
        $plugin->categories()->sync([$category->id]);
        $plugin->tags()->sync([$tag->id]);

        Livewire::test(ViewPlugin::class, ['record' => $plugin->id])
            ->assertSuccessful()
            ->assertSee('cloud-deployer-tool')
            ->assertSee('Cloud Deployer Tool')
            ->assertSee('Plugin Creator')
            ->assertSee('creator@example.com')
            ->assertSee('DevOps')
            ->assertSee('cloud')
            ->assertActionExists('delete');
    }

    public function test_admin_can_delete_plugin_from_view_page(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'tool-to-delete-from-view']);

        Livewire::test(ViewPlugin::class, ['record' => $plugin->id])
            ->set('previousUrl', 'http://localhost/admin/plugins?tablePage=2')
            ->assertActionVisible('delete')
            ->callAction('delete')
            ->assertNotified('Plugin deleted successfully.')
            ->assertRedirect('http://localhost/admin/plugins?tablePage=2');

        $this->assertTrue($plugin->fresh()->trashed());
    }

    public function test_delete_plugin_redirects_to_index_when_previous_url_is_same_view_page(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'plugin-self-ref-view']);
        $viewUrl = PluginResource::getUrl('view', ['record' => $plugin]);

        Livewire::test(ViewPlugin::class, ['record' => $plugin->id])
            ->set('previousUrl', $viewUrl)
            ->callAction('delete')
            ->assertRedirect(PluginResource::getUrl('index'));

        $this->assertTrue($plugin->fresh()->trashed());
    }

    public function test_restore_action_does_not_exist_on_view_page(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'trashed-tool-no-restore']);
        $plugin->delete();

        Livewire::test(ViewPlugin::class, ['record' => $plugin->id])
            ->assertActionDoesNotExist('restore');

        $this->assertFalse(PluginResource::canRestore($plugin));
        $this->assertFalse(PluginResource::canRestoreAny());
    }

    public function test_admin_can_navigate_to_view_page_from_plugins_table(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'navigable-plugin']);

        $expectedUrl = PluginResource::getUrl('view', ['record' => $plugin]);

        $this->assertStringContainsString($plugin->id, $expectedUrl);
    }

    public function test_admin_can_delete_approved_plugin_from_plugins_table(): void
    {
        $approvedPlugin = Plugin::factory()->create([
            'name' => 'approved-plugin-to-delete',
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Livewire::test(ListPlugins::class)
            ->assertTableActionExists('delete')
            ->assertTableActionVisible('delete', $approvedPlugin)
            ->callTableAction('delete', $approvedPlugin)
            ->assertNotified('Plugin deleted successfully.');

        $this->assertTrue($approvedPlugin->fresh()->trashed());
    }

    public function test_admin_can_delete_reported_plugin_from_view_report_page(): void
    {
        $reporter = User::factory()->create();
        $plugin = Plugin::factory()->create(['name' => 'offending-plugin']);

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Malicious behaviors detected in source link.',
        ]);

        Livewire::test(ViewPluginReport::class, ['record' => $report->id])
            ->set('previousUrl', 'http://localhost/admin/plugins/plugin-reports?tablePage=3')
            ->assertActionExists('deletePlugin')
            ->callAction('deletePlugin')
            ->assertNotified('Plugin deleted successfully.')
            ->assertRedirect('http://localhost/admin/plugins/plugin-reports?tablePage=3');

        $this->assertTrue($plugin->fresh()->trashed());
    }

    public function test_admin_can_dismiss_report_from_view_report_page_and_redirect_to_previous_page(): void
    {
        $reporter = User::factory()->create();
        $plugin = Plugin::factory()->create(['name' => 'reported-plugin']);

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Inappropriate content.',
        ]);

        Livewire::test(ViewPluginReport::class, ['record' => $report->id])
            ->set('previousUrl', 'http://localhost/admin/plugins/plugin-reports?tablePage=2')
            ->assertActionExists('delete')
            ->callAction('delete')
            ->assertNotified('Report dismissed successfully')
            ->assertRedirect('http://localhost/admin/plugins/plugin-reports?tablePage=2');

        $this->assertTrue($report->fresh()->trashed());
    }

    public function test_dismiss_report_redirects_to_index_when_previous_url_is_same_view_page(): void
    {
        $reporter = User::factory()->create();
        $plugin = Plugin::factory()->create(['name' => 'reported-plugin-self']);

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Inappropriate content.',
        ]);

        $viewUrl = ViewPluginReport::getResource()::getUrl('view', ['record' => $report]);

        Livewire::test(ViewPluginReport::class, ['record' => $report->id])
            ->set('previousUrl', $viewUrl)
            ->callAction('delete')
            ->assertRedirect(ViewPluginReport::getResource()::getUrl('index'));

        $this->assertTrue($report->fresh()->trashed());
    }

    public function test_admin_can_view_plugin_report_details_and_full_plugin_context(): void
    {
        $author = User::factory()->create(['name' => 'Bad Actor', 'email' => 'bad@example.com']);
        $reporter = User::factory()->create(['name' => 'Good Citizen', 'email' => 'citizen@example.com']);
        $category = Category::create(['name' => 'Security']);
        $tag = Tag::create(['name' => 'scanner']);

        $plugin = Plugin::factory()->create([
            'user_id' => $author->id,
            'name' => 'suspicious-scanner',
            'title' => 'Suspicious Scanner',
            'license' => 'Apache-2.0',
            'source_link' => 'https://github.com/bad/scanner',
            'view_count' => 88,
        ]);
        $plugin->categories()->sync([$category->id]);
        $plugin->tags()->sync([$tag->id]);

        $report = PluginReport::create([
            'user_id' => $reporter->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Contains suspicious network calls.',
        ]);

        Livewire::test(ViewPluginReport::class, ['record' => $report->id])
            ->assertSuccessful()
            // Report Details (at top)
            ->assertSee('Report Details')
            ->assertSee('Good Citizen')
            ->assertSee('citizen@example.com')
            ->assertSee('Contains suspicious network calls.')
            // Plugin Overview
            ->assertSee('Plugin Overview')
            ->assertSee('suspicious-scanner')
            ->assertSee('Suspicious Scanner')
            ->assertSee('Security')
            ->assertSee('scanner')
            // Author Information
            ->assertSee('Author Information')
            ->assertSee('Bad Actor')
            ->assertSee('bad@example.com')
            // Statistics & Engagement
            ->assertSee('Statistics & Engagement')
            // Timeline & Dates
            ->assertSee('Timeline & Dates')
            // Header actions
            ->assertActionExists('deletePlugin')
            ->assertActionDoesNotExist('viewPlugin')
            ->assertActionDoesNotExist('restorePlugin')
            ->assertActionExists('delete');
    }
}
