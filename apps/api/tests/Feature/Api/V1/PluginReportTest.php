<?php

namespace Tests\Feature\Api\V1;

use App\Models\Plugin;
use App\Models\PluginReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_report_plugin(): void
    {
        $plugin = Plugin::factory()->create();

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'This plugin contains malicious code.',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_report_a_plugin_successfully(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'This plugin violates terms of service.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Plugin reported successfully. Our moderators will review it shortly.')
            ->assertJsonPath('data.report.reason', 'This plugin violates terms of service.')
            ->assertJsonPath('data.report.plugin_id', $plugin->id)
            ->assertJsonPath('data.report.user_id', $user->id);

        $this->assertDatabaseHas('plugin_reports', [
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'reason' => 'This plugin violates terms of service.',
        ]);
    }

    public function test_user_cannot_report_the_same_plugin_twice(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        // First report
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'This is a spam plugin.',
        ])->assertStatus(201);

        // Second report by same user
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'Reporting this plugin for a second time.',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'You have already reported this plugin.');

        $this->assertDatabaseCount('plugin_reports', 1);
    }

    public function test_different_users_can_report_the_same_plugin(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $this->actingAs($user1, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'Report from first user.',
        ])->assertStatus(201);

        $this->actingAs($user2, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'Report from second user.',
        ])->assertStatus(201);

        $this->assertDatabaseCount('plugin_reports', 2);
    }

    public function test_report_fails_if_reason_is_missing(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_report_fails_if_reason_is_too_short(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => 'Too short', // < 10 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_report_fails_if_reason_is_too_long(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/plugins/{$plugin->id}/reports", [
            'reason' => str_repeat('a', 1001), // > 1000 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_report_non_existent_plugin_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/plugins/01930000-0000-7000-8000-000000000000/reports', [
            'reason' => 'Reporting non-existent plugin.',
        ]);

        $response->assertStatus(404);
    }
}

