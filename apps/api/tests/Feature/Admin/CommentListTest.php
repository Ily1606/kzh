<?php

namespace Tests\Feature\Admin;

use App\Enums\PluginStatus;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the admin-only comment list at /admin/comments.
 *
 * The page is view-only: it lists comments, can be narrowed by plugin and by
 * the owning plugin's status, and expands a comment into a read-only modal.
 */
class CommentListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_non_admins_cannot_open_the_comment_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/comments')->assertForbidden();
    }

    public function test_admins_can_open_the_comment_list_page(): void
    {
        $this->get('/admin/comments')->assertOk();
    }

    public function test_the_table_exposes_only_the_expected_columns(): void
    {
        Livewire::test(ListComments::class)
            ->assertSuccessful()
            ->assertTableColumnExists('plugin.name')
            ->assertTableColumnExists('author.name')
            ->assertTableColumnExists('content')
            ->assertTableColumnExists('created_at');
    }

    public function test_the_content_cell_is_width_capped_and_clamped_to_two_lines(): void
    {
        Comment::factory()->create([
            'content' => str_repeat('a long comment body ', 30),
        ]);

        $html = Livewire::test(ListComments::class)->html();

        // Neither `lineClamp()` nor `wrap()` constrains the column width on their
        // own, so the cap has to come from the cell's own inline style.
        $this->assertStringContainsString('max-width: 25rem', $html);
        $this->assertStringContainsString('overflow-wrap: break-word', $html);
        $this->assertStringContainsString('--line-clamp: 2', $html);
    }

    public function test_secondary_columns_are_hidden_until_an_admin_toggles_them_on(): void
    {
        Comment::factory()->count(2)->create();

        $optional = ['plugin.status', 'author.email', 'hidden_at', 'updated_at'];

        Livewire::test(ListComments::class)
            ->assertTableColumnExists('content');

        // Hidden by default: present in the schema, absent from the rendered rows.
        foreach ($optional as $column) {
            Livewire::test(ListComments::class)
                ->assertTableColumnExists($column)
                ->assertCanNotRenderTableColumn($column);
        }

        Livewire::test(ListComments::class)
            ->toggleAllTableColumns()
            ->assertCanRenderTableColumn('hidden_at')
            ->assertCanRenderTableColumn('plugin.status');
    }

    public function test_the_hidden_at_column_falls_back_to_a_placeholder_when_not_moderated(): void
    {
        $hidden = Comment::factory()->hidden()->create();
        Comment::factory()->create();

        Livewire::test(ListComments::class)
            ->toggleAllTableColumns()
            ->assertCanRenderTableColumn('hidden_at')
            ->assertSee(__('comment.table.values.visible'))
            ->assertSee($hidden->fresh()->hidden_at->format('d/m/Y H:i'));
    }

    public function test_the_table_lists_a_comment_with_its_plugin_and_author(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'Awesome Plugin']);
        $author = User::factory()->create(['name' => 'Jane Doe']);
        $comment = Comment::factory()
            ->for($plugin)
            ->for($author, 'author')
            ->create(['content' => 'Great plugin, thanks!']);

        Livewire::test(ListComments::class)
            ->assertCanSeeTableRecords([$comment])
            ->assertSee('Awesome Plugin')
            ->assertSee('Jane Doe')
            ->assertSee('Great plugin, thanks!');
    }

    public function test_the_table_is_sorted_by_created_at_descending_by_default(): void
    {
        $plugin = Plugin::factory()->create();

        $older = Comment::factory()->for($plugin)->create();
        $newer = Comment::factory()->for($plugin)->create();

        // created_at is not mass assignable, so drive the timestamps directly.
        Comment::query()->whereKey($older->id)->update(['created_at' => now()->subDay()]);
        Comment::query()->whereKey($newer->id)->update(['created_at' => now()]);

        Livewire::test(ListComments::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_soft_deleted_comments_are_excluded(): void
    {
        $kept = Comment::factory()->create();
        $trashed = Comment::factory()->create();
        $trashed->delete();

        Livewire::test(ListComments::class)
            ->assertCanSeeTableRecords([$kept])
            ->assertCanNotSeeTableRecords([$trashed]);
    }

    public function test_the_list_can_be_filtered_by_plugin(): void
    {
        $wanted = Plugin::factory()->create();
        $other = Plugin::factory()->create();

        $wantedComment = Comment::factory()->for($wanted)->create();
        $otherComment = Comment::factory()->for($other)->create();

        Livewire::test(ListComments::class)
            ->filterTable('plugin', $wanted)
            ->assertCanSeeTableRecords([$wantedComment])
            ->assertCanNotSeeTableRecords([$otherComment]);
    }

    public function test_the_list_can_be_filtered_by_plugin_status(): void
    {
        $approvedPlugin = Plugin::factory()->create(['status' => PluginStatus::Approved]);
        $pendingPlugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $approvedComment = Comment::factory()->for($approvedPlugin)->create();
        $pendingComment = Comment::factory()->for($pendingPlugin)->create();

        Livewire::test(ListComments::class)
            ->filterTable('status', PluginStatus::Approved)
            ->assertCanSeeTableRecords([$approvedComment])
            ->assertCanNotSeeTableRecords([$pendingComment]);
    }

    public function test_the_plugin_and_status_filters_can_be_combined(): void
    {
        $approved = Plugin::factory()->create(['status' => PluginStatus::Approved]);
        $pending = Plugin::factory()->create(['status' => PluginStatus::Pending]);
        $otherApproved = Plugin::factory()->create(['status' => PluginStatus::Approved]);

        $approvedComment = Comment::factory()->for($approved)->create();
        $pendingComment = Comment::factory()->for($pending)->create();
        $otherApprovedComment = Comment::factory()->for($otherApproved)->create();

        Livewire::test(ListComments::class)
            ->filterTable('plugin', $approved)
            ->filterTable('status', PluginStatus::Approved)
            ->assertCanSeeTableRecords([$approvedComment])
            ->assertCanNotSeeTableRecords([$pendingComment, $otherApprovedComment]);
    }

    public function test_the_content_cell_is_clickable_and_opens_the_view_modal(): void
    {
        $comment = Comment::factory()->create(['content' => 'Short body']);

        // Filament renders a column that carries an Action as a button whose
        // click mounts that action — this is the click-to-expand affordance.
        $html = html_entity_decode(Livewire::test(ListComments::class)->html());

        $this->assertStringContainsString(
            "mountTableAction('view', '{$comment->getKey()}')",
            $html,
        );
    }

    public function test_the_modal_shows_the_full_comment_detail(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'Awesome Plugin']);
        $author = User::factory()->create(['name' => 'Jane Doe']);

        // Long enough that the 2-line clamp in the cell hides the tail, so the
        // assertion below can only pass if the modal renders the whole body.
        $content = 'Start of the comment. '.str_repeat('filler words ', 40).'END OF COMMENT';

        $comment = Comment::factory()
            ->for($plugin)
            ->for($author, 'author')
            ->create(['content' => $content]);

        Livewire::test(ListComments::class)
            ->mountTableAction('view', $comment)
            ->assertTableActionMounted([
                ['name' => 'view', 'context' => ['recordKey' => $comment->getKey()]],
            ])
            ->assertMountedActionModalSee('Awesome Plugin')
            ->assertMountedActionModalSee('Jane Doe')
            ->assertMountedActionModalSee('END OF COMMENT');
    }

    public function test_the_modal_reports_when_a_comment_is_hidden(): void
    {
        $hidden = Comment::factory()->hidden()->create(['content' => 'moderated comment']);

        Livewire::test(ListComments::class)
            ->assertCanSeeTableRecords([$hidden])
            ->mountTableAction('view', $hidden)
            ->assertTableActionMounted([
                ['name' => 'view', 'context' => ['recordKey' => $hidden->getKey()]],
            ])
            ->assertMountedActionModalSee(__('comment.table.modal.hidden_at'));
    }

    public function test_the_modal_omits_the_hidden_at_row_for_visible_comments(): void
    {
        $comment = Comment::factory()->create();

        Livewire::test(ListComments::class)
            ->mountTableAction('view', $comment)
            ->assertMountedActionModalDontSee(__('comment.table.modal.hidden_at'));
    }
}
