<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\PluginRepositoryInterface;
use App\Enums\PluginStatus;
use App\Models\Comment;
use App\Models\Plugin;
use App\Models\User;
use App\Repositories\PluginRepository;
use App\Services\CommentService;
use App\Support\RequestContext;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * The 201 payload must expose `replies_count` as an integer even though the
     * create path never computes it — a brand-new comment cannot have replies
     * yet, so the resource falls back to 0. Both list endpoints report the same
     * field as an integer, and the client compares it with the replies paginator
     * total, so a null here would break that contract.
     */
    public function test_a_new_comment_reports_zero_replies(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.comment.replies_count', 0);
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
    public function test_a_rejected_reply_does_not_touch_the_plugin_counter(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => '00000000-0000-0000-0000-000000000000',
        ]))->assertNotFound();

        $this->assertSame(0, $plugin->fresh()->comment_count);
    }

    // -----------------------------------------------------------------------
    // Transaction: the insert and the counter update are all-or-nothing
    // -----------------------------------------------------------------------

    /**
     * The comment row and `plugins.comment_count` must move together. If the
     * counter update blows up mid-transaction, leaving a committed comment whose
     * counter was never bumped, that drift is permanent — nothing in the codebase
     * reconciles it.
     *
     * The real service is kept; only the plugin repository is doubled, so the
     * failure is injected at exactly the point the transaction is supposed to
     * cover. `comment_count` is the only counter the create path writes: the
     * reply count is computed by the list queries, never stored.
     */
    public function test_a_failing_counter_update_rolls_back_the_whole_comment(): void
    {
        $user = User::factory()->create();
        $plugin = $this->approvedPlugin();
        $parent = Comment::factory()->create(['plugin_id' => $plugin->id]);

        // Let every real call through, then fail the plugin counter.
        $real = new PluginRepository($this->app);

        $failing = new class($real) implements PluginRepositoryInterface
        {
            public function __construct(private readonly PluginRepository $real) {}

            public function getModel(): string
            {
                return $this->real->getModel();
            }

            public function findApprovedById(string $id): Plugin
            {
                return $this->real->findApprovedById($id);
            }

            public function findApprovedByIds(array $ids): Collection
            {
                return $this->real->findApprovedByIds($ids);
            }

            public function incrementViewCount(string $id, int $count): void
            {
                $this->real->incrementViewCount($id, $count);
            }

            public function incrementCommentCount(string $pluginId): void
            {
                throw new RuntimeException('comment counter exploded');
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function create(array $attributes): Plugin
            {
                return $this->real->create($attributes);
            }

            public function getPaginatedApprovedPlugins(int $perPage): LengthAwarePaginator
            {
                return $this->real->getPaginatedApprovedPlugins($perPage);
            }

            public function getTrendingPlugins(int $daysLimit, array $weights, float $gravity, float $ageOffset, int $limit): Collection
            {
                return $this->real->getTrendingPlugins($daysLimit, $weights, $gravity, $ageOffset, $limit);
            }

            public function getTopAllTimePlugins(int $limit): Collection
            {
                return $this->real->getTopAllTimePlugins($limit);
            }
        };

        $this->app->instance(PluginRepositoryInterface::class, $failing);

        try {
            $this->app->make(CommentService::class)->create($user, $plugin->id, [
                'content' => 'This should never persist.',
                'parent_comment_id' => $parent->id,
            ], new RequestContext(null, null));

            $this->fail('Expected the service to propagate the failure.');
        } catch (RuntimeException) {
            // Expected — the counter update blew up inside the transaction.
        }

        $this->assertDatabaseCount('comments', 1); // only the parent survives
        $this->assertSame(0, $plugin->fresh()->comment_count);
    }

    // -----------------------------------------------------------------------
    // Thread depth limit
    // -----------------------------------------------------------------------

    /**
     * The three levels that must keep working: top-level, reply, sub-reply.
     * Each one goes through the HTTP API so the guard itself is exercised, not
     * just the factory.
     */
    public function test_allows_comments_at_each_level_up_to_the_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload())
            ->assertCreated();

        $reply = $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => Comment::whereNull('parent_comment_id')->firstOrFail()->id,
        ]))->assertCreated();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $reply->json('data.comment.id'),
        ]))->assertCreated();

        $this->assertDatabaseCount('comments', 3);
    }

    /**
     * A sub-reply is the deepest level allowed (max_depth 3), so replying *to*
     * one would create a fourth level and must be refused.
     */
    public function test_rejects_reply_to_a_comment_at_the_maximum_depth(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();
        $subReply = Comment::factory()->replyTo($reply)->create();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $subReply->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_comment_id'])
            ->assertJsonPath('errors.parent_comment_id.0', __('api.comment_max_depth_reached'));

        $this->assertDatabaseCount('comments', 3); // nothing new persisted
        $this->assertSame(0, $plugin->fresh()->comment_count);
    }

    /**
     * The limit is enforced on the *create* path only. A thread that was
     * already deeper than the limit (built here through the factory, as
     * pre-existing data would be) stays fully readable — the read endpoints
     * walk whatever tree exists and must not start 404ing on it.
     */
    public function test_reads_still_work_on_a_thread_deeper_than_the_limit(): void
    {
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $child = Comment::factory()->replyTo($root)->create();
        $grandchild = Comment::factory()->replyTo($child)->create();
        $tooDeep = Comment::factory()->replyTo($grandchild)->create();

        $this->getJson("/api/v1/plugins/{$plugin->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $root->id);

        $this->getJson("/api/v1/plugins/{$plugin->id}/comments/{$root->id}/replies")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $child->id);

        $this->getJson("/api/v1/plugins/{$plugin->id}/comments/{$grandchild->id}/replies")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $tooDeep->id);
    }

    /**
     * The depth guard reads the configured limit, so lowering it tightens the
     * rule without a code change.
     */
    public function test_max_depth_is_configurable(): void
    {
        config()->set('comments.max_depth', 1);

        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $root->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_comment_id']);

        $this->assertDatabaseCount('comments', 1);
    }

    /**
     * A reply must not be rejected for depth when the limit is generous, even
     * on a thread that is already at the default limit — this pins that the
     * guard compares against the config value rather than a hardcoded 3.
     */
    public function test_accepts_a_deep_reply_when_the_limit_is_raised(): void
    {
        config()->set('comments.max_depth', 10);

        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $root = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $reply = Comment::factory()->replyTo($root)->create();
        $subReply = Comment::factory()->replyTo($reply)->create();

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $subReply->id,
        ]))->assertCreated();

        $this->assertDatabaseCount('comments', 4);
    }

    /**
     * A malformed tree where a comment points back at its own ancestor must not
     * hang the request: the depth walk is bounded by the limit, so it gives up
     * and reports the thread as too deep.
     */
    public function test_a_cyclic_thread_is_rejected_instead_of_looping_forever(): void
    {
        config()->set('comments.max_depth', 50);

        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $a = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $b = Comment::factory()->replyTo($a)->create();

        // Close the loop: the root now claims the reply as its parent.
        Comment::withoutGlobalScopes()->whereKey($a->id)->update(['parent_comment_id' => $b->id]);

        $this->postJson("/api/v1/plugins/{$plugin->id}/comments", $this->payload([
            'parent_comment_id' => $b->id,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_comment_id']);

        $this->assertDatabaseCount('comments', 2);
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
