<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The list endpoint is Reddit-style: it only returns the highest-level comments
 * (`parent_comment_id IS NULL`) of an approved plugin, paginated, and every
 * thread carries the `replies_count` the client needs to decide whether to call
 * the replies endpoint.
 */
class ListCommentsTest extends TestCase
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

    private function url(Plugin $plugin, string $query = ''): string
    {
        return "/api/v1/plugins/{$plugin->id}/comments{$query}";
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

    public function test_returns_paginated_root_comments_for_approved_plugin(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->count(3)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.comments')
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
    }

    /**
     * The envelope owns the page state, so Laravel's own `links` block must not
     * leak in next to it — the client would otherwise have two sources of truth.
     */
    public function test_does_not_expose_laravel_pagination_links(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->count(3)->create(['plugin_id' => $plugin->id]);

        $response = $this->getJson($this->url($plugin))->assertOk();

        $this->assertArrayNotHasKey('links', $response->json());
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_meta_describes_a_single_full_page(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->count(3)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', (int) config('comments.pagination.default_per_page'))
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 3)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_from_and_to_are_null_on_an_empty_page(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->count(2)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, '?page=9'))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.current_page', 9)
            ->assertJsonPath('meta.from', null)
            ->assertJsonPath('meta.to', null)
            ->assertJsonPath('meta.total', 2);
    }

    // -----------------------------------------------------------------------
    // Reddit-style tree: only root comments
    // -----------------------------------------------------------------------

    public function test_only_returns_root_comments(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($parent)->create();
        Comment::factory()->count(2)->create(['plugin_id' => $plugin->id]);

        $response = $this->getJson($this->url($plugin))->assertOk();

        $this->assertNotContains($reply->id, $this->returnedIds($response));
        $this->assertCount(3, $this->returnedIds($response));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_meta_total_counts_threads_not_rows(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->count(4)->replyTo($parent)->create();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_replies_of_another_plugin_are_not_counted(): void
    {
        $pluginA = $this->approvedPlugin();
        $pluginB = $this->approvedPlugin();

        $parentOnB = Comment::factory()->create(['plugin_id' => $pluginB->id]);
        Comment::factory()->count(3)->replyTo($parentOnB)->create();

        $this->getJson($this->url($pluginA))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.total', 0);
    }

    // -----------------------------------------------------------------------
    // replies_count
    // -----------------------------------------------------------------------

    public function test_exposes_the_number_of_visible_direct_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $withReplies = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $withoutReplies = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Comment::factory()->count(2)->replyTo($withReplies)->create();

        $response = $this->getJson($this->url($plugin, '?sort=oldest'))->assertOk();

        $this->assertSame($withReplies->id, $response->json('data.comments.0.id'));
        $this->assertSame(2, $response->json('data.comments.0.replies_count'));
        $this->assertSame($withoutReplies->id, $response->json('data.comments.1.id'));
        $this->assertSame(0, $response->json('data.comments.1.replies_count'));
    }

    public function test_replies_count_ignores_hidden_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Comment::factory()->replyTo($parent)->create();
        Comment::factory()->hidden()->replyTo($parent)->create();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    public function test_replies_count_ignores_soft_deleted_replies(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $deleted = Comment::factory()->replyTo($parent)->create();
        $deleted->delete();

        Comment::factory()->replyTo($parent)->create();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    public function test_replies_count_does_not_include_grandchildren(): void
    {
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $child = Comment::factory()->replyTo($parent)->create();
        Comment::factory()->replyTo($child)->create();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonPath('data.comments.0.replies_count', 1);
    }

    // -----------------------------------------------------------------------
    // Sorting
    // -----------------------------------------------------------------------

    public function test_orders_comments_newest_first_by_default(): void
    {
        $plugin = $this->approvedPlugin();
        $oldest = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()->subMinutes(5)]);
        $middle = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()->subMinutes(2)]);
        $newest = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()]);

        $response = $this->getJson($this->url($plugin))->assertOk();

        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            $this->returnedIds($response),
        );
    }

    public function test_orders_comments_oldest_first_when_requested(): void
    {
        $plugin = $this->approvedPlugin();
        $oldest = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()->subMinutes(5)]);
        $middle = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()->subMinutes(2)]);
        $newest = Comment::factory()->create(['plugin_id' => $plugin->id, 'created_at' => now()]);

        $response = $this->getJson($this->url($plugin, '?sort=oldest'))->assertOk();

        $this->assertSame(
            [$oldest->id, $middle->id, $newest->id],
            $this->returnedIds($response),
        );
    }

    /**
     * Rows sharing a timestamp are common (bulk seeds, imports), so the tie must
     * be broken deterministically — otherwise page 2 can repeat or skip a row.
     */
    public function test_breaks_created_at_ties_by_id(): void
    {
        $plugin = $this->approvedPlugin();
        $createdAt = now();

        $created = collect(range(1, 3))
            ->map(fn () => Comment::factory()->create([
                'plugin_id' => $plugin->id,
                'created_at' => $createdAt,
            ]));

        $response = $this->getJson($this->url($plugin, '?sort=oldest'))->assertOk();

        $this->assertSame(
            $created->sortBy('id')->pluck('id')->all(),
            $this->returnedIds($response),
        );
    }

    // -----------------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------------

    public function test_paginates_root_comments_across_pages(): void
    {
        config()->set('comments.pagination.default_per_page', 2);

        $plugin = $this->approvedPlugin();
        Comment::factory()->count(5)->create([
            'plugin_id' => $plugin->id,
            'created_at' => now(),
        ]);

        $first = $this->getJson($this->url($plugin, '?sort=oldest'))->assertOk();
        $second = $this->getJson($this->url($plugin, '?sort=oldest&page=2'))->assertOk();
        $last = $this->getJson($this->url($plugin, '?sort=oldest&page=3'))->assertOk();

        $this->assertCount(2, $this->returnedIds($first));
        $this->assertCount(2, $this->returnedIds($second));
        $this->assertCount(1, $this->returnedIds($last));

        // Every root comment is returned exactly once across the three pages.
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
            ->assertJsonPath('meta.from', 5)
            ->assertJsonPath('meta.to', 5)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_uses_the_configured_default_page_size_when_per_page_is_omitted(): void
    {
        config()->set('comments.pagination.default_per_page', 2);

        $plugin = $this->approvedPlugin();
        Comment::factory()->count(5)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonCount(2, 'data.comments')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_honours_an_explicit_per_page(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->count(4)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, '?per_page=3'))
            ->assertOk()
            ->assertJsonCount(3, 'data.comments')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    /**
     * An oversized page size is clamped rather than rejected, so a greedy client
     * degrades to the cap instead of getting a 422.
     */
    public function test_clamps_per_page_to_the_configured_maximum(): void
    {
        config()->set('comments.pagination.max_per_page', 3);

        $plugin = $this->approvedPlugin();
        Comment::factory()->count(10)->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, '?per_page=9999'))
            ->assertOk()
            ->assertJsonCount(3, 'data.comments')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 10);
    }

    // -----------------------------------------------------------------------
    // Visibility
    // -----------------------------------------------------------------------

    public function test_does_not_return_hidden_comments(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->create(['plugin_id' => $plugin->id]);
        Comment::factory()->hidden()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_does_not_return_soft_deleted_comments(): void
    {
        $plugin = $this->approvedPlugin();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $comment->delete();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_returns_empty_list_when_plugin_has_no_visible_comments(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson($this->url($plugin))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_does_not_require_authentication(): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin))->assertOk();
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function test_rejects_per_page_below_one(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson($this->url($plugin, '?per_page=0'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_rejects_a_negative_per_page(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson($this->url($plugin, '?per_page=-5'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_rejects_a_non_integer_per_page(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson($this->url($plugin, '?per_page=abc'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Validation lives in the FormRequest, so it runs before the controller asks
     * the repository whether the plugin exists. A malformed query string is a 422
     * even when the plugin in the URL is a non-existent one — the replies
     * endpoint has the mirror of this against its parent comment.
     */
    public function test_validation_runs_before_the_plugin_is_resolved(): void
    {
        $this->getJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000/comments?per_page=0')
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
            'empty string' => [''],
        ];
    }

    #[DataProvider('unsupportedSortProvider')]
    public function test_rejects_an_unsupported_sort(string $sort): void
    {
        $plugin = $this->approvedPlugin();

        $this->getJson($this->url($plugin, '?sort='.$sort))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function supportedSortProvider(): array
    {
        return [
            'newest' => ['newest'],
            'oldest' => ['oldest'],
        ];
    }

    #[DataProvider('supportedSortProvider')]
    public function test_accepts_every_supported_sort(string $sort): void
    {
        $plugin = $this->approvedPlugin();
        Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->getJson($this->url($plugin, '?sort='.$sort))->assertOk();
    }

    // -----------------------------------------------------------------------
    // Plugin validation
    // -----------------------------------------------------------------------

    public function test_returns_404_for_non_existent_plugin(): void
    {
        $this->getJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000/comments')
            ->assertNotFound();
    }

    public function test_returns_404_for_pending_plugin(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->getJson($this->url($plugin))->assertNotFound();
    }

    public function test_does_not_return_comments_from_another_plugin(): void
    {
        $pluginA = $this->approvedPlugin();
        $pluginB = $this->approvedPlugin();
        Comment::factory()->count(3)->create(['plugin_id' => $pluginB->id]);

        $this->getJson($this->url($pluginA))
            ->assertOk()
            ->assertJsonCount(0, 'data.comments')
            ->assertJsonPath('meta.total', 0);
    }
}
