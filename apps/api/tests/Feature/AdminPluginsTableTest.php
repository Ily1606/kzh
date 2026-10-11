<?php

namespace Tests\Feature;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Filament\Resources\Plugins\Pages\ListPlugins;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin plugin list reads `star_count` and `comment_count` straight off the
 * record, the way Filament resolves every column's state. Neither is a column:
 * they are counts over `stars` and `comments`, so the table query has to alias
 * them or the columns render blank.
 *
 * The sort assertions exist because the failure was not cosmetic. Filament
 * orders by the bare column name, and without the alias in the select list
 * PostgreSQL rejects `ORDER BY "star_count"` outright — the Stars header threw a
 * 500 instead of sorting.
 */
class AdminPluginsTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($this->admin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pluginWith(array $overrides = []): Plugin
    {
        return Plugin::factory()->create(array_merge([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ], $overrides));
    }

    /**
     * Both counts come from rows, so a test that wants a plugin to "have N" has
     * to create them — one distinct user per star, since the composite primary
     * key forbids the same user starring twice.
     */
    private function starTimes(Plugin $plugin, int $count): void
    {
        User::factory()->count($count)->create()->each(function (User $stargazer) use ($plugin): void {
            DB::table('stars')->insert([
                'plugin_id' => $plugin->id,
                'user_id' => $stargazer->id,
            ]);
        });
    }

    private function commentTimes(Plugin $plugin, int $count): void
    {
        Comment::factory()->count($count)->create(['plugin_id' => $plugin->id]);
    }

    public function test_the_star_column_shows_the_number_of_star_rows(): void
    {
        $plugin = $this->pluginWith();
        $this->starTimes($plugin, 3);

        Livewire::test(ListPlugins::class)
            ->assertCanSeeTableRecords([$plugin])
            ->assertTableColumnStateSet('star_count', 3, $plugin);
    }

    public function test_the_comment_column_shows_the_number_of_visible_comment_rows(): void
    {
        $plugin = $this->pluginWith();
        $this->commentTimes($plugin, 2);
        Comment::factory()->hidden()->create(['plugin_id' => $plugin->id]);

        Livewire::test(ListPlugins::class)
            ->assertTableColumnStateSet('comment_count', 2, $plugin);
    }

    /**
     * A plugin nobody has touched still has to render a number rather than an
     * empty cell. Asserting "zero" through the Filament helper would not catch
     * that: `assertEquals(0, null)` passes, so the missing-alias case slips
     * through. The strict assertion is made on the query result instead, where
     * `null` and `0` are distinguishable.
     */
    public function test_a_plugin_with_no_interactions_still_has_both_counts(): void
    {
        $this->pluginWith();

        $record = app(PluginRepositoryInterface::class)
            ->getPaginatedApprovedPlugins(15)
            ->first();

        $this->assertSame(0, $record->star_count);
        $this->assertSame(0, $record->comment_count);
    }

    /**
     * `view_count` stays a real column: no table holds the fact, so it has to be
     * stored. It is asserted here so a future "derive everything" pass does not
     * break it by looking for a `views` table that will never exist.
     */
    public function test_the_view_column_reads_the_stored_column(): void
    {
        $plugin = $this->pluginWith(['view_count' => 120]);

        Livewire::test(ListPlugins::class)
            ->assertTableColumnStateSet('view_count', 120, $plugin);
    }

    public function test_sorting_by_the_star_column_returns_rows_in_that_order(): void
    {
        $popular = $this->pluginWith();
        $this->starTimes($popular, 5);

        $quiet = $this->pluginWith();

        Livewire::test(ListPlugins::class)
            ->sortTable('star_count', 'desc')
            ->assertCanSeeTableRecords([$popular, $quiet], inOrder: true);
    }

    public function test_sorting_by_the_comment_column_returns_rows_in_that_order(): void
    {
        $discussed = $this->pluginWith();
        $this->commentTimes($discussed, 4);

        $quiet = $this->pluginWith();

        Livewire::test(ListPlugins::class)
            ->sortTable('comment_count', 'desc')
            ->assertCanSeeTableRecords([$discussed, $quiet], inOrder: true);
    }

    /**
     * A sort on the stored column keeps working too — the derived ones must not
     * have come at the cost of the real one.
     */
    public function test_sorting_by_the_view_column_returns_rows_in_that_order(): void
    {
        $popular = $this->pluginWith(['view_count' => 900]);
        $quiet = $this->pluginWith(['view_count' => 1]);

        Livewire::test(ListPlugins::class)
            ->sortTable('view_count', 'desc')
            ->assertCanSeeTableRecords([$popular, $quiet], inOrder: true);
    }

    /**
     * The three review actions used to render as three labelled links spanning
     * the row. They now hang off one vertical-ellipsis trigger, so the only
     * thing occupying the cell is that icon. The icon's name does not survive
     * rendering — Filament inlines the SVG — so what is asserted is the shape
     * that proves the grouping happened: a single icon-button trigger carrying
     * an accessible name, with the three actions demoted to dropdown items
     * behind it.
     */
    public function test_the_review_actions_render_behind_a_vertical_ellipsis_trigger(): void
    {
        $this->pluginWith(['status' => PluginStatus::Pending, 'approved_at' => null]);

        $html = Livewire::test(ListPlugins::class)->html();

        $this->assertStringContainsString('fi-ac-icon-btn-group', $html);
        $this->assertStringContainsString('aria-label="Actions"', $html);

        foreach (['Reject', 'Approve', 'Request changes'] as $label) {
            $this->assertStringContainsString(
                "<span class=\"fi-dropdown-list-item-label\">{$label}</span>",
                $html,
                "The [{$label}] action is no longer inside the group dropdown.",
            );
        }
    }

    /**
     * Grouping must not resurrect the actions on a row that has already been
     * decided. An `ActionGroup` hides itself once every action inside it is
     * hidden, so a reviewed plugin gets no trigger cell at all rather than an
     * ellipsis that opens onto an empty menu.
     */
    public function test_the_review_action_group_is_absent_once_the_plugin_is_reviewed(): void
    {
        $this->pluginWith();

        $html = Livewire::test(ListPlugins::class)->html();

        $this->assertStringNotContainsString('fi-ac-icon-btn-group', $html);
        $this->assertStringNotContainsString('aria-label="Actions"', $html);
    }

    /**
     * The actions live inside the group's dropdown now, but the table still has
     * to find them by name: `pushRecordActions()` flattens a group into the
     * table's action cache, which is what every review test drives. Without this
     * a refactor to a group would look like it worked until someone clicked.
     */
    public function test_the_grouped_actions_are_still_reachable_by_name(): void
    {
        $pending = $this->pluginWith(['status' => PluginStatus::Pending, 'approved_at' => null]);

        Livewire::test(ListPlugins::class)
            ->assertTableActionVisible('approve', $pending)
            ->assertTableActionVisible('reject', $pending)
            ->assertTableActionVisible('requestChanges', $pending);
    }
}
