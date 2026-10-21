<?php

namespace Tests\Feature;

use App\Enums\PluginEventType;
use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\PluginEvent;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPluginViewInfolistTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    public function test_view_action_is_available_and_clickable_on_table_rows(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $html = html_entity_decode(Livewire::test(ListPlugins::class)->html());

        $this->assertStringContainsString(
            "mountTableAction('view', '{$plugin->getKey()}')",
            $html,
        );
    }

    public function test_table_displays_only_main_columns_by_default(): void
    {
        $test = Livewire::test(ListPlugins::class);
        $columns = $test->instance()->getTable()->getColumns();

        $this->assertFalse($columns['name']->isToggledHiddenByDefault());
        $this->assertFalse($columns['user.name']->isToggledHiddenByDefault());
        $this->assertFalse($columns['status']->isToggledHiddenByDefault());

        $this->assertTrue($columns['title']->isToggledHiddenByDefault());
        $this->assertTrue($columns['categories.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['tags.name']->isToggledHiddenByDefault());
        $this->assertTrue($columns['star_count']->isToggledHiddenByDefault());
        $this->assertTrue($columns['view_count']->isToggledHiddenByDefault());
        $this->assertTrue($columns['comment_count']->isToggledHiddenByDefault());
    }

    public function test_view_modal_displays_full_plugin_information(): void
    {
        $author = User::factory()->create([
            'name' => 'Octocat Developer',
            'email' => 'octocat@github.example.com',
        ]);

        $fullDescription = 'This is an extensive multi-sentence description of the plugin that exceeds normal column limits to verify full visibility.';

        $plugin = Plugin::factory()->create([
            'name' => 'advanced-code-assistant',
            'title' => $fullDescription,
            'user_id' => $author->id,
            'license' => 'Apache-2.0',
            'source_link' => 'https://github.com/octocat/advanced-code-assistant',
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
            'view_count' => 1250,
        ]);

        $category = Category::firstOrCreate(['name' => 'Developer Tools']);
        $plugin->categories()->syncWithoutDetaching([$category->id]);

        $tag = Tag::firstOrCreate(['name' => 'copilot-agent']);
        $plugin->tags()->syncWithoutDetaching([$tag->id]);

        PluginEvent::create([
            'plugin_id' => $plugin->id,
            'admin_id' => $this->admin->id,
            'event_type' => PluginEventType::Approved,
            'message' => 'Approved after testing source code integration.',
        ]);

        Livewire::test(ListPlugins::class)
            ->mountTableAction('view', $plugin)
            ->assertTableActionMounted([
                ['name' => 'view', 'context' => ['recordKey' => $plugin->getKey()]],
            ])
            ->assertMountedActionModalSee('advanced-code-assistant')
            ->assertMountedActionModalSee($fullDescription)
            ->assertMountedActionModalSee('Octocat Developer')
            ->assertMountedActionModalSee('octocat@github.example.com')
            ->assertMountedActionModalSee('Developer Tools')
            ->assertMountedActionModalSee('copilot-agent')
            ->assertMountedActionModalSee('Apache-2.0')
            ->assertMountedActionModalSee('https://github.com/octocat/advanced-code-assistant')
            ->assertMountedActionModalSee('Approved after testing source code integration.');
    }

    public function test_view_modal_shows_review_rejection_history(): void
    {
        $author = User::factory()->create(['name' => 'Author User']);
        $plugin = Plugin::factory()->create([
            'name' => 'rejected-plugin',
            'title' => 'A plugin that needs work',
            'user_id' => $author->id,
            'status' => PluginStatus::Rejected,
            'approved_at' => null,
        ]);

        PluginEvent::create([
            'plugin_id' => $plugin->id,
            'admin_id' => $this->admin->id,
            'event_type' => PluginEventType::Rejected,
            'message' => 'Repository link returned 404. Please fix before resubmitting.',
        ]);

        Livewire::test(ListPlugins::class)
            ->mountTableAction('view', $plugin)
            ->assertMountedActionModalSee('rejected-plugin')
            ->assertMountedActionModalSee('Repository link returned 404. Please fix before resubmitting.');
    }
}

