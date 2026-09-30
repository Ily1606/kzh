<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\CommentRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CreateCommentTest extends TestCase
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
            'comment_count' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge(['content' => 'This is a test comment.'], $overrides);
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function test_authenticated_user_can_create_a_comment(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('api.comment_created_successfully'))
            ->assertJsonStructure([
                'data' => [
                    'comment' => [
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
            ]);

        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseHas('comments', [
            'plugin_id' => $plugin->id,
            'content' => 'This is a test comment.',
        ]);
    }

    public function test_comment_increments_plugin_comment_count(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->assertSame(0, $plugin->fresh()->comment_count);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        $this->assertSame(1, $plugin->fresh()->comment_count);
    }

    public function test_can_reply_to_an_existing_comment(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $parent->id,
        ]))->assertCreated()
            ->assertJsonPath('data.comment.parent_comment_id', $parent->id);

        $this->assertDatabaseHas('comments', [
            'plugin_id' => $plugin->id,
            'parent_comment_id' => $parent->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // replies_count denormalisation
    // -----------------------------------------------------------------------

    /**
     * Regression test: `create()` never receives `replies_count` among the
     * attributes it is handed, so the model has no in-memory value for the
     * column and `CommentResource` used to serialise it as null — making the 201
     * payload disagree with the list endpoints, which expose the same field as an
     * integer. `CommentService::create()` refreshes the row to fix that.
     */
    public function test_a_new_comment_starts_with_zero_replies(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.comment.replies_count', 0);

        $this->assertDatabaseHas('comments', ['replies_count' => 0]);
    }

    public function test_replying_increments_the_parent_replies_count(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->assertSame(0, $parent->fresh()->replies_count);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $parent->id,
        ]))->assertCreated();

        $this->assertSame(1, $parent->fresh()->replies_count);
    }

    public function test_repeated_replies_keep_incrementing_the_parent_counter(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($user);

        foreach (range(1, 3) as $ignored) {
            $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
                'parent_comment_id' => $parent->id,
            ]))->assertCreated();
        }

        $this->assertSame(3, $parent->fresh()->replies_count);
    }

    public function test_a_reply_does_not_increment_its_own_replies_count(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($user);

        $replyId = $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $parent->id,
        ]))->assertCreated()->json('data.comment.id');

        $this->assertSame(0, Comment::findOrFail($replyId)->replies_count);
    }

    public function test_creating_a_root_comment_leaves_other_counters_untouched(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id, 'replies_count' => 2]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        $this->assertSame(2, $parent->fresh()->replies_count);
    }

    public function test_a_deep_chain_of_replies_increments_each_ancestor(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);

        Sanctum::actingAs($user);

        $childId = $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $root->id,
        ]))->assertCreated()->json('data.comment.id');

        $grandchildId = $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $childId,
        ]))->assertCreated()->json('data.comment.id');

        // Only the direct parent is bumped; the root keeps counting the child.
        $this->assertSame(1, $root->fresh()->replies_count);
        $this->assertSame(1, Comment::findOrFail($childId)->replies_count);
        $this->assertSame(0, Comment::findOrFail($grandchildId)->replies_count);
    }

    // -----------------------------------------------------------------------
    // comment_count counts every row, thread or reply
    // -----------------------------------------------------------------------

    public function test_a_reply_also_increments_the_plugin_comment_count(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $parent->id,
        ]))->assertCreated();

        // plugin.comment_count is a row count, not a thread count.
        $this->assertSame(1, $plugin->fresh()->comment_count);
    }

    public function test_response_contains_correct_author_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.comment.author.id', $user->id)
            ->assertJsonPath('data.comment.author.name', $user->name);
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('comments', 0);
    }

    // -----------------------------------------------------------------------
    // Plugin validation
    // -----------------------------------------------------------------------

    public function test_returns_404_for_non_existent_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000/comments', $this->payload())
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.not_found'));
    }

    /**
     * Regression: the 404 is raised by `findOrFail()`, so Laravel throws a
     * ModelNotFoundException whose message is generated by the framework —
     * "No query results for model [App\Models\Plugin] {uuid}". The exception
     * renderer must translate that into the public message instead of echoing
     * the internal one, which would confirm the model class and the exact id
     * that failed to resolve.
     */
    public function test_returns_404_for_pending_plugin_without_leaking_internal_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.not_found'));

        $this->assertStringNotContainsString(Plugin::class, $response->getContent());
        $this->assertStringNotContainsString($plugin->id, $response->getContent());
    }

    // -----------------------------------------------------------------------
    // parent_comment_id validation
    // -----------------------------------------------------------------------

    public function test_returns_404_when_parent_comment_belongs_to_another_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $pluginA = $this->approvedPlugin();
        $pluginB = $this->approvedPlugin();
        $parentOnB = Comment::factory()->create(['plugin_id' => $pluginB->id]);

        $this->postJson("/api/v1/plugins/{$pluginA->id}/comments", $this->payload([
            'parent_comment_id' => $parentOnB->id,
        ]))->assertNotFound();

        $this->assertDatabaseCount('comments', 1); // only the parent exists
    }

    public function test_returns_404_when_parent_comment_does_not_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => '00000000-0000-0000-0000-000000000000',
        ]))->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
    }

    public function test_returns_404_when_parent_comment_is_hidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $hidden = Comment::factory()->hidden()->create(['plugin_id' => $plugin->id]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $hidden->id,
        ]))->assertNotFound();
    }

    public function test_returns_404_when_parent_comment_is_soft_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $deleted = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $deleted->delete();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $deleted->id,
        ]))->assertNotFound();

        $this->assertDatabaseCount('comments', 1); // only the deleted parent exists
    }

    /**
     * A rejected reply must not leave a half-applied counter behind.
     */
    public function test_a_rejected_reply_does_not_touch_the_parent_counter(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id, 'replies_count' => 1]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => '00000000-0000-0000-0000-000000000000',
        ]))->assertNotFound();

        $this->assertSame(1, $parent->fresh()->replies_count);
    }

    // -----------------------------------------------------------------------
    // Transaction: the insert and both counter updates are all-or-nothing
    // -----------------------------------------------------------------------

    /**
     * The comment row, `plugins.comment_count` and the parent's `replies_count`
     * must move together. If a later step blows up mid-transaction, leaving a
     * committed comment whose counters were never bumped, that drift is permanent
     * — nothing in the codebase reconciles it.
     *
     * The real service is kept; only the repository is doubled, so the failure is
     * injected at exactly the point the transaction is supposed to cover.
     */
    public function test_a_failing_counter_update_rolls_back_the_whole_comment(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        // Let every real call through, then fail the reply counter.
        $real = new CommentRepository($this->app);

        $failing = new class($real) implements CommentRepositoryInterface
        {
            public function __construct(private readonly CommentRepository $real) {}

            public function getModel(): string
            {
                return $this->real->getModel();
            }

            public function paginateRootByPlugin(string $pluginId, int $perPage, string $sort): LengthAwarePaginator
            {
                return $this->real->paginateRootByPlugin($pluginId, $perPage, $sort);
            }

            public function paginateRepliesByParent(string $parentCommentId, int $perPage, string $sort): LengthAwarePaginator
            {
                return $this->real->paginateRepliesByParent($parentCommentId, $perPage, $sort);
            }

            public function findVisibleByIdAndPlugin(string $id, string $pluginId): Comment
            {
                return $this->real->findVisibleByIdAndPlugin($id, $pluginId);
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function create(array $attributes): Comment
            {
                return $this->real->create($attributes);
            }

            public function incrementRepliesCount(string $commentId): void
            {
                throw new RuntimeException('reply counter exploded');
            }
        };

        $this->app->instance(CommentRepositoryInterface::class, $failing);

        try {
            $this->app->make(CommentService::class)->create($user, $plugin->id, [
                'content' => 'This should never persist.',
                'parent_comment_id' => $parent->id,
            ]);

            $this->fail('Expected the service to propagate the failure.');
        } catch (RuntimeException) {
            // Expected — the counter update blew up inside the transaction.
        }

        $this->assertDatabaseCount('comments', 1); // only the parent survives
        $this->assertSame(0, $plugin->fresh()->comment_count);
        $this->assertSame(0, $parent->fresh()->replies_count);
    }

    // -----------------------------------------------------------------------
    // Input validation
    // -----------------------------------------------------------------------

    public function test_rejects_missing_content(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_rejects_whitespace_only_content(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", ['content' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_rejects_content_exceeding_max_length(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", [
            'content' => str_repeat('a', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_accepts_content_exactly_at_max_length(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", [
            'content' => str_repeat('a', 2000),
        ])->assertCreated();
    }

    public function test_rejects_invalid_uuid_for_parent_comment_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => 'not-a-uuid',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_comment_id']);
    }

    public function test_accepts_null_parent_comment_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => null,
        ]))->assertCreated()
            ->assertJsonPath('data.comment.parent_comment_id', null);
    }

    // -----------------------------------------------------------------------
    // Rate limiting
    // -----------------------------------------------------------------------

    public function test_create_comment_is_rate_limited_per_user(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $limit = (int) config('comments.create_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
                ->assertCreated();
        }

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertTooManyRequests();
    }

    public function test_rate_limit_never_blocks_everything_when_config_is_invalid(): void
    {
        config()->set('comments.create_per_minute', 0);
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();
    }
}
