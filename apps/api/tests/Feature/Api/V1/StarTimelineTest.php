<?php

namespace Tests\Feature\Api\V1;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StarTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_404_when_plugin_does_not_exist(): void
    {
        $nonExistentId = Str::uuid()->toString();

        $response = $this->getJson("/api/v1/plugins/{$nonExistentId}/stars/timeline");

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_plugin_id_is_invalid_uuid(): void
    {
        // Because of the ->whereUuid('pluginId') constraint on the route, 
        // an invalid UUID will not match the route and return 404 Not Found.
        $response = $this->getJson('/api/v1/plugins/invalid-uuid-format/stars/timeline');

        $response->assertStatus(404);
    }
    
    public function test_it_returns_successful_empty_timeline_if_no_stars(): void
    {
        // This is a success case but good to ensure it doesn't break
        $plugin = Plugin::factory()->create();

        $response = $this->getJson("/api/v1/plugins/{$plugin->id}/stars/timeline");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => []
                 ]);
    }
}

