<?php

namespace Tests\Feature\Admin;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommentModerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that deleting a comment will cascade soft-delete all child replies and related reports.
     */
    public function test_cascade_delete_soft_deletes_comment_and_descendants_and_reports(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $parent = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $child = Comment::factory()->replyTo($parent)->create([
            'author_id' => $user->id,
        ]);

        $parentReport = CommentReport::create([
            'user_id' => $user->id,
            'comment_id' => $parent->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Spam',
        ]);

        $childReport = CommentReport::create([
            'user_id' => $user->id,
            'comment_id' => $child->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Inappropriate',
        ]);

        $parent->cascadeDelete();

        $this->assertSoftDeleted('comments', ['id' => $parent->id]);
        $this->assertSoftDeleted('comments', ['id' => $child->id]);
        $this->assertSoftDeleted('comment_reports', ['id' => $parentReport->id]);
        $this->assertSoftDeleted('comment_reports', ['id' => $childReport->id]);
    }

    /**
     * Test that once a comment is deleted, it cannot be restored.
     */
    public function test_deleted_comment_cannot_be_restored(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $comment->delete();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);

        $trashed = Comment::withTrashed()->find($comment->id);
        $restoreResult = $trashed->restore();

        // Must refuse to restore
        $this->assertFalse($restoreResult);
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    /**
     * Test that hiding a parent comment cascades to replies, and unhiding also cascades.
     */
    public function test_cascade_hide_and_cascade_unhide(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $parent = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $child = Comment::factory()->replyTo($parent)->create([
            'author_id' => $user->id,
        ]);

        $this->assertNull($parent->hidden_at);
        $this->assertNull($child->hidden_at);

        // Hide parent comment (cascades to child)
        $parent->cascadeHide();

        $parent->refresh();
        $child->refresh();

        $this->assertNotNull($parent->hidden_at);
        $this->assertNotNull($child->hidden_at);

        // Unhide parent comment (cascades to child)
        $parent->cascadeUnhide();

        $parent->refresh();
        $child->refresh();

        $this->assertNull($parent->hidden_at);
        $this->assertNull($child->hidden_at);
    }

    /**
     * Test that once a comment report is deleted (dismissed), it cannot be restored.
     */
    public function test_deleted_comment_report_cannot_be_restored(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $report = CommentReport::create([
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'plugin_id' => $plugin->id,
            'reason' => 'Spam',
        ]);

        $report->delete();
        $this->assertSoftDeleted('comment_reports', ['id' => $report->id]);

        $trashed = CommentReport::withTrashed()->find($report->id);
        $restoreResult = $trashed->restore();

        // Must refuse to restore
        $this->assertFalse($restoreResult);
        $this->assertSoftDeleted('comment_reports', ['id' => $report->id]);
    }

    /**
     * Test that cascadeDelete is idempotent and guards against double-deletion when already trashed.
     */
    public function test_cascade_delete_is_idempotent_when_comment_already_trashed(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $comment = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        \Illuminate\Support\Facades\Redis::set($statKey, 5);

        // 1st cascadeDelete
        $comment->cascadeDelete();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertEquals(4, (int) \Illuminate\Support\Facades\Redis::get($statKey));

        // 2nd cascadeDelete on already trashed instance
        $comment->cascadeDelete();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        // Redis count must NOT be decremented again
        $this->assertEquals(4, (int) \Illuminate\Support\Facades\Redis::get($statKey));
    }

    /**
     * Test that cascadeDelete decrements trending score for both parent and all replies in batch.
     */
    public function test_cascade_delete_decrements_trending_for_all_replies_in_batch(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $parent = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);
        $child1 = Comment::factory()->replyTo($parent)->create(['author_id' => $user->id]);
        $child2 = Comment::factory()->replyTo($parent)->create(['author_id' => $user->id]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        \Illuminate\Support\Facades\Redis::set($statKey, 3);

        $parent->cascadeDelete();

        $this->assertSoftDeleted('comments', ['id' => $parent->id]);
        $this->assertSoftDeleted('comments', ['id' => $child1->id]);
        $this->assertSoftDeleted('comments', ['id' => $child2->id]);
        $this->assertEquals(0, (int) \Illuminate\Support\Facades\Redis::get($statKey));
    }

    /**
     * Test that cascadeHide and cascadeUnhide adjust trending score for all replies in batch.
     */
    public function test_cascade_hide_and_unhide_adjusts_trending_for_all_replies_in_batch(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $parent = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);
        $child1 = Comment::factory()->replyTo($parent)->create(['author_id' => $user->id]);
        $child2 = Comment::factory()->replyTo($parent)->create(['author_id' => $user->id]);

        $date = now()->format('Y-m-d');
        $statKey = "trending:stats:comments:{$date}:{$plugin->id}";
        \Illuminate\Support\Facades\Redis::set($statKey, 3);

        $parent->cascadeHide();
        $this->assertNotNull($parent->fresh()->hidden_at);
        $this->assertNotNull($child1->fresh()->hidden_at);
        $this->assertNotNull($child2->fresh()->hidden_at);
        $this->assertEquals(0, (int) \Illuminate\Support\Facades\Redis::get($statKey));

        $parent->fresh()->cascadeUnhide();
        $this->assertNull($parent->fresh()->hidden_at);
        $this->assertNull($child1->fresh()->hidden_at);
        $this->assertNull($child2->fresh()->hidden_at);
        $this->assertEquals(3, (int) \Illuminate\Support\Facades\Redis::get($statKey));
    }

    /**
     * Test that cascadeUnhide does not unhide replies that were hidden separately before or after.
     */
    public function test_cascade_unhide_does_not_unhide_replies_hidden_separately(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create(['user_id' => $user->id]);

        $parent = Comment::factory()->create([
            'plugin_id' => $plugin->id,
            'author_id' => $user->id,
        ]);
        // child_before was hidden 10 minutes before parent
        $childBefore = Comment::factory()->replyTo($parent)->create([
            'author_id' => $user->id,
            'hidden_at' => now()->subMinutes(10),
        ]);
        // child_cascade was active and should be hidden/unhidden with parent
        $childCascade = Comment::factory()->replyTo($parent)->create([
            'author_id' => $user->id,
            'hidden_at' => null,
        ]);

        // Hide parent
        $parent->cascadeHide();
        $this->assertNotNull($parent->fresh()->hidden_at);
        $this->assertNotNull($childCascade->fresh()->hidden_at);

        // child_after was hidden separately after parent was hidden (e.g. spam)
        $childAfter = Comment::factory()->replyTo($parent)->create([
            'author_id' => $user->id,
            'hidden_at' => now()->addMinutes(5),
        ]);

        // Unhide parent
        $parent->fresh()->cascadeUnhide();

        // Parent and child_cascade should be unhidden
        $this->assertNull($parent->fresh()->hidden_at);
        $this->assertNull($childCascade->fresh()->hidden_at);

        // child_before and child_after MUST remain hidden
        $this->assertNotNull($childBefore->fresh()->hidden_at);
        $this->assertNotNull($childAfter->fresh()->hidden_at);
    }
}

