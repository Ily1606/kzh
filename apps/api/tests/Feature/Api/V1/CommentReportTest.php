<?php

namespace Tests\Feature\Api\V1;

use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_report_comment(): void
    {
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => 'This is a spam comment.',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_report_a_comment_successfully(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => 'This comment is very inappropriate.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Comment reported successfully. Our moderators will review it shortly.')
            ->assertJsonPath('data.report.reason', 'This comment is very inappropriate.');

        $this->assertDatabaseHas('comment_reports', [
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'plugin_id' => $plugin->id,
            'reason' => 'This comment is very inappropriate.',
        ]);
    }

    public function test_user_cannot_report_the_same_comment_twice(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        // First report
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => 'This is a spam comment.',
        ])->assertStatus(201);

        // Second report
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => 'I am reporting this again.',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'You have already reported this comment.');
            
        // Ensure only one report exists
        $this->assertDatabaseCount('comment_reports', 1);
    }

    public function test_report_fails_if_reason_is_missing(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_report_fails_if_reason_is_too_short(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => 'Too short', // less than 10 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
    
    public function test_report_fails_if_reason_is_too_long(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment->id}/reports", [
            'reason' => str_repeat('A', 1001), // More than 1000 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_report_fails_if_plugin_does_not_exist(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(); // creates its own plugin
        $fakePluginId = Str::uuid()->toString();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$fakePluginId}/comments/{$comment->id}/reports", [
            'reason' => 'This is a spam comment.',
        ]);

        $response->assertStatus(404);
    }

    public function test_report_fails_if_comment_does_not_exist(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $fakeCommentId = Str::uuid()->toString();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$fakeCommentId}/reports", [
            'reason' => 'This is a spam comment.',
        ]);

        $response->assertStatus(404);
    }
    
    public function test_report_fails_if_comment_belongs_to_another_plugin(): void
    {
        $user = User::factory()->create();
        $plugin1 = Plugin::factory()->create();
        $plugin2 = Plugin::factory()->create();
        
        // Comment belongs to plugin 2
        $comment = Comment::factory()->create(['plugin_id' => $plugin2->id]);

        // Try to report comment using plugin 1's URL
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin1->id}/comments/{$comment->id}/reports", [
            'reason' => 'This is a spam comment.',
        ]);

        $response->assertStatus(404); // Because Comment::where('plugin_id', $plugin->id)->findOrFail() will throw
    }
    
    public function test_user_can_report_multiple_different_comments(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $comment1 = Comment::factory()->create(['plugin_id' => $plugin->id]);
        $comment2 = Comment::factory()->create(['plugin_id' => $plugin->id]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment1->id}/reports", [
            'reason' => 'Spam comment 1 here',
        ])->assertStatus(201);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/comments/{$comment2->id}/reports", [
            'reason' => 'Spam comment 2 here',
        ])->assertStatus(201);
        
        $this->assertDatabaseCount('comment_reports', 2);
    }
}
