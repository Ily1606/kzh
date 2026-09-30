<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The replies endpoint serves one level of the tree at a time, for any depth:
 * the client passes the ID of whichever comment it is expanding. It shares
 * `ListCommentsRequest` and the repository's `paginateLevel()` with the list
 * endpoint, so the tests here deliberately mirror the list ones to prove the
 * two cannot drift apart.
 */
class ListCommentRepliesTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    private function url(Plugin $plugin, Comment $comment, string $query = ''): string
    {
        return "/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/replies{$query}";
    }

    /**
     * @return array<int, string>
     */
    private function returnedIds(mixed $response): array
    {
        return array_column($response->json('data.comments') ?? [], 'id');
    }

    // -----------------------------------------------------------------------
    // Response envelope
    // -----------------------------------------------------------------------

    public function test_returns_direct_replies_of_a_comment(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($parent)->create();
        $another = Comment::factory()->replyTo($parent)->create();

        $response = $this->getJson($this->url($plugin, $parent))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comments.0.parent_comment_id', $parent->id)
            ->assertJsonPath('data.comments.1.parent_comment_id', $parent->id)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'comments' => [
                        '*' => [
                            'id',
                            'plugin_id',
                            'parent_comment_id',
                            'content',
                            'author' => ['id', 'name', 'avatar_url'],
                            'replies_count',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertCount(2, $this->returnedIds($response));
        $this->assertEqualsCanonicalizing(
            [$reply->id, $another->id],
            $this->returnedIds($response),
        );
    }

    public function test_does_not_expose_laravel_pagination_links(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->replyTo($parent)->create();

        $response = $this->getJson($this->url($plugin, $parent))->assertOk();

        $this->assertArrayNotHasKey('links', $response->json());
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_meta_total_matches_the_number_of_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->count(3)->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $parent))
            ->assertOk()
            ->assertJsonCount(3, 'data.comments')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 3);
    }

    public function test_replies_count_of_the_list_matches_the_replies_total(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $visible = Comment::factory()->replyTo($parent)->create();
        Comment::factory()->hidden()->replyTo($parent)->create();
        $deleted = Comment::factory()->replyTo($parent)->create();
        $deleted->delete();

        // The client compares this number with meta.total before rendering
        // "View N replies", so the two endpoints must not disagree.
        $listed = $this->getJson("/api/v1/plugins/{$plugin->id}/comments")->assertOk();
        $replies = $this->getJson($this->url($plugin, $parent))->assertOk();

        $this->assertSame(1, $listed->json('data.comments.0.replies_count'));
        $this->assertSame(1, $replies->json('meta.total'));
        $this->assertSame([$visible->id], $this->returnedIds($replies));
    }

    // -----------------------------------------------------------------------
    // replies_count of a nested comment
    //
    // A reply row has to carry its own counter: that is what tells the client
    // the thread below it is worth expanding. It is computed rather than read
    // from the stored column (factory-created trees never bump that column), so
    // every assertion here only passes if the count comes from the query.
    // -----------------------------------------------------------------------

    public function test_a_reply_exposes_the_number_of_its_own_visible_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();

        Comment::factory()->count(3)->replyTo($reply)->create();

        $this->getJson($this->url($plugin, $root))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 3);
    }

    public function test_a_reply_without_children_reports_zero(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->replyTo($root)->create();

        $this->getJson($this->url($plugin, $root))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 0);
    }

    public function test_a_reply_replies_count_ignores_hidden_children(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();

        Comment::factory()->replyTo($reply)->create();
        Comment::factory()->hidden()->replyTo($reply)->create();

        $this->getJson($this->url($plugin, $root))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    public function test_a_reply_replies_count_ignores_soft_deleted_children(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();

        $deleted = Comment::factory()->replyTo($reply)->create();
        $deleted->delete();

        Comment::factory()->replyTo($reply)->create();

        $this->getJson($this->url($plugin, $root))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    public function test_a_reply_replies_count_does_not_include_grandchildren(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();

        $child = Comment::factory()->replyTo($reply)->create();
        Comment::factory()->replyTo($child)->create();

        $this->getJson($this->url($plugin, $root))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    // -----------------------------------------------------------------------
    // One level deep, at any depth
    // -----------------------------------------------------------------------

    public function test_only_returns_direct_replies_not_the_whole_subtree(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $child = Comment::factory()->replyTo($parent)->create();
        $grandchild = Comment::factory()->replyTo($child)->create();

        $response = $this->getJson($this->url($plugin, $parent))->assertOk();

        $this->assertSame([$child->id], $this->returnedIds($response));
        $this->assertNotContains($grandchild->id, $this->returnedIds($response));
    }

    /**
     * The same URL shape works for a reply too, which is what makes arbitrarily
     * deep trees expandable with one endpoint.
     */
    public function test_serves_every_depth_of_the_tree(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $child = Comment::factory()->replyTo($root)->create();
        $grandchild = Comment::factory()->replyTo($child)->create();

        $this->getJson($this->url($plugin, $child))
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.id', $grandchild->id)
            ->assertJsonPath('data.comments.0.parent_comment_id', $child->id);
    }

    public function test_returns_empty_list_when_comment_has_no_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $parent))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_does_not_return_sibling_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $firstChild = Comment::factory()->replyTo($parent)->create();
        $secondChild = Comment::factory()->replyTo($parent)->create();
        $ownChild = Comment::factory()->replyTo($firstChild)->create();

        // The list is deliberately non-empty. Asserting a sibling is absent from
        // an empty list can never fail, which would make this test worthless.
        $response = $this->getJson($this->url($plugin, $firstChild))->assertOk();

        $this->assertSame([$ownChild->id], $this->returnedIds($response));
        $this->assertNotContains($secondChild->id, $this->returnedIds($response));
        $this->assertNotContains($parent->id, $this->returnedIds($response));
    }

    /**
     * The parent is resolved through the plugin, so a caller cannot walk another
     * plugin's tree by guessing a comment ID.
     */
    public function test_returns_404_when_parent_belongs_to_another_plugin(): void
    {
        $pluginA = $this->approvedPlugin();
        $pluginB = $this->approvedPlugin();

        $parentOnB = Comment::factory()->create(['plugin_id' => $pluginB->id]);
        Comment::factory()->replyTo($parentOnB)->create();

        $this->getJson($this->url($pluginA, $parentOnB))->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Visibility
    // -----------------------------------------------------------------------

    public function test_does_not_return_hidden_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->replyTo($parent)->create();
        Comment::factory()->hidden()->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $parent))
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_does_not_return_soft_deleted_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $deleted = Comment::factory()->replyTo($parent)->create();
        $deleted->delete();
        Comment::factory()->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $parent))
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_hidden_reply_is_itself_not_expandable(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $hidden = Comment::factory()->hidden()->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $hidden))->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Sorting
    // -----------------------------------------------------------------------

    public function test_orders_replies_newest_first_by_default(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $oldest = Comment::factory()->replyTo($parent)->create(['created_at' => now()->subMinutes(5)]);
        $newest = Comment::factory()->replyTo($parent)->create(['created_at' => now()]);

        $response = $this->getJson($this->url($plugin, $parent))->assertOk();

        $this->assertSame([$newest->id, $oldest->id], $this->returnedIds($response));
    }

    public function test_orders_replies_oldest_first_when_requested(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $oldest = Comment::factory()->replyTo($parent)->create(['created_at' => now()->subMinutes(5)]);
        $newest = Comment::factory()->replyTo($parent)->create(['created_at' => now()]);

        $response = $this->getJson($this->url($plugin, $parent, '?sort=oldest'))->assertOk();

        $this->assertSame([$oldest->id, $newest->id], $this->returnedIds($response));
    }

    // -----------------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------------

    public function test_paginates_replies_across_pages(): void
    {
        config()->set('comments.pagination.default_per_page', 2);

        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->count(5)->replyTo($parent)->create(['created_at' => now()]);

        $first = $this->getJson($this->url($plugin, $parent, '?sort=oldest'))->assertOk();
        $second = $this->getJson($this->url($plugin, $parent, '?sort=oldest&page=2'))->assertOk();
        $last = $this->getJson($this->url($plugin, $parent, '?sort=oldest&page=3'))->assertOk();

        $this->assertCount(2, $this->returnedIds($first));
        $this->assertCount(2, $this->returnedIds($second));
        $this->assertCount(1, $this->returnedIds($last));

        $this->assertSame(
            5,
            count(array_unique([
                ...$this->returnedIds($first),
                ...$this->returnedIds($second),
                ...$this->returnedIds($last),
            ])),
        );

        $last->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_clamps_per_page_to_the_configured_maximum(): void
    {
        config()->set('comments.pagination.max_per_page', 2);

        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->count(5)->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $parent, '?per_page=9999'))
            ->assertOk()
            ->assertJsonCount(2, 'data.comments')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function test_rejects_per_page_below_one(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $parent, '?per_page=0'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_rejects_a_non_integer_per_page(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $parent, '?per_page=abc'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsupportedSortProvider(): array
    {
        return [
            'top is not implemented yet' => ['top'],
            'random is not a sort strategy' => ['random'],
        ];
    }

    #[DataProvider('unsupportedSortProvider')]
    public function test_rejects_an_unsupported_sort(string $sort): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $parent, '?sort='.$sort))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    /**
     * Validation runs before the parent lookup, so a malformed query string is a
     * 422 even when the comment does not exist.
     */
    public function test_validation_runs_before_the_parent_is_resolved(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson("/api/v1/plugins/{$plugin->id}/comments/00000000-0000-0000-0000-000000000000/replies?per_page=0")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    // -----------------------------------------------------------------------
    // Plugin / comment validation
    // -----------------------------------------------------------------------

    public function test_returns_404_for_non_existent_plugin(): void
    {
        $this->getJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000/comments/00000000-0000-0000-0000-000000000000/replies')
            ->assertNotFound();
    }

    public function test_returns_404_for_pending_plugin(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $parent))->assertNotFound();
    }

    public function test_returns_404_when_parent_comment_does_not_exist(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson("/api/v1/plugins/{$plugin->id}/comments/00000000-0000-0000-0000-000000000000/replies")
            ->assertNotFound();
    }

    public function test_returns_404_when_parent_comment_is_hidden(): void
    {
        $plugin = $this->approvedPlugin();
        $hidden = Comment::factory()->hidden()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, $hidden))->assertNotFound();
    }

    public function test_returns_404_when_parent_comment_is_soft_deleted(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->replyTo($parent)->create();
        $parent->delete();

        $this->getJson($this->url($plugin, $parent))->assertNotFound();
    }

    public function test_does_not_require_authentication(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->replyTo($parent)->create();

        $this->getJson($this->url($plugin, $parent))->assertOk();
    }
}
