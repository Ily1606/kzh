<?php

namespace Tests\Unit\Repositories;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Http\Resources\PluginResource;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `comment_count` is no longer a column — it is a COUNT over `comments`. These
 * tests pin the two properties the reviewer asked for: rows removed outside the
 * comment endpoint (a moderation hide, a cascade delete, a manual DB fix) are
 * reflected immediately, with no counter to drift.
 *
 * The counter covers *visible* comments only, matching what
 * `CommentRepository::paginateLevel()` already reports as `replies_count` and
 * what the client renders next to the plugin.
 */
class PluginCommentCountTest extends TestCase
{
    use RefreshDatabase;

    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    private function giveComments(Plugin $plugin, int $count): void
    {
        Comment::factory()->count($count)->create(['plugin_id' => $plugin->id]);
    }

    public function test_the_count_is_the_number_of_visible_comment_rows(): void
    {
        $this->giveComments($this->plugin, 3);

        $this->assertSame(3, $this->plugin->comments()->count());
        $this->assertSame(3, $this->listCount());
    }

    public function test_rows_removed_outside_the_comment_endpoint_shrink_the_count(): void
    {
        $this->giveComments($this->plugin, 3);

        // Simulate a moderation write that never goes through CommentService:
        // the count must follow the rows, not a counter.
        Comment::query()->where('plugin_id', $this->plugin->id)->limit(2)->delete();

        $this->assertSame(1, $this->plugin->comments()->count());
        $this->assertSame(1, $this->listCount());
    }

    /**
     * A hidden comment is one no visitor can read, so it must not inflate the
     * number shown next to the plugin — the same rule `replies_count` follows.
     */
    public function test_a_hidden_comment_is_not_counted(): void
    {
        $this->giveComments($this->plugin, 3);

        Comment::factory()->hidden()->create(['plugin_id' => $this->plugin->id]);

        $this->assertSame(4, $this->plugin->comments()->count());
        $this->assertSame(3, $this->listCount());
    }

    public function test_a_soft_deleted_comment_is_not_counted(): void
    {
        $this->giveComments($this->plugin, 2);

        Comment::query()->where('plugin_id', $this->plugin->id)->first()->delete();

        $this->assertSame(1, $this->listCount());
    }

    /**
     * The count follows the rows even when the author is cascade-deleted: the
     * comments table is the only source of truth, so removing the rows is
     * enough to move the number.
     */
    public function test_comments_removed_by_a_cascade_leave_nothing_behind(): void
    {
        $author = User::factory()->create();
        Comment::factory()->create([
            'plugin_id' => $this->plugin->id,
            'author_id' => $author->id,
        ]);
        $this->giveComments($this->plugin, 2);

        $this->assertSame(3, $this->listCount());

        $author->forceDelete();

        $this->assertDatabaseCount('comments', 2);
        $this->assertSame(2, $this->listCount());
    }

    /**
     * The detail endpoint does not get the count from the list query — it has no
     * alias to read and counts the relation itself. Two code paths for one
     * number is exactly how they drift, so they are pinned against each other:
     * a plugin with two readable comments and one moderated must report 2 from
     * either side.
     */
    public function test_the_detail_endpoint_and_the_list_agree(): void
    {
        $this->giveComments($this->plugin, 2);
        Comment::factory()->hidden()->create(['plugin_id' => $this->plugin->id]);
        Comment::factory()->create([
            'plugin_id' => $this->plugin->id,
        ])->delete();

        $this->assertSame(2, $this->listCount());
        $this->assertSame(2, $this->detailCount());
    }

    public function test_the_count_never_goes_negative_because_it_is_a_count(): void
    {
        $this->giveComments($this->plugin, 1);

        Comment::query()->where('plugin_id', $this->plugin->id)->delete();
        Comment::query()->where('plugin_id', $this->plugin->id)->delete();

        $this->assertSame(0, $this->listCount());
    }

    /**
     * The list endpoint is where a stale counter would surface to users: it must
     * report the same number as a direct COUNT.
     *
     * Returned without a cast on purpose. The assertions use `assertSame`, and
     * `(int) null` is 0 — casting here would let a missing alias pass as "zero
     * comments" instead of failing, which is the exact failure mode this class
     * exists to catch.
     */
    private function listCount(): mixed
    {
        return app(PluginRepositoryInterface::class)
            ->getPaginatedApprovedPlugins(15)
            ->first()
            ->comment_count;
    }

    /**
     * The detail endpoint's number: `findApprovedById()` returns no alias, so
     * `PluginResource` counts the relation itself.
     */
    private function detailCount(): int
    {
        $resource = new PluginResource(
            app(PluginRepositoryInterface::class)->findApprovedById($this->plugin->id)
        );

        return (int) $resource->resolve()['comment_count'];
    }
}
