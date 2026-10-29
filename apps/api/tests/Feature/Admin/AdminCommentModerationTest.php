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
}

