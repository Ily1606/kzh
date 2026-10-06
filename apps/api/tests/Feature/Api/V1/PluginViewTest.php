<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PluginViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::del('plugins:views_buffer');
    }

    public function test_view_01_authenticated_user_first_view(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'api');

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'view_count' => 0,
        ]);

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 1);

        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $plugin->id));
        $this->assertTrue(Cache::has("plugin_view:{$plugin->id}:{$user->id}"));
    }

    public function test_view_02_authenticated_user_view_again_within_24h(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'api');

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        // First view
        $this->postJson("/api/v1/plugins/{$plugin->id}/view")->assertOk();

        // Second view
        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ignored')
            ->assertJsonPath('data.view_count', 1);

        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_03_authenticated_user_view_again_after_ttl(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'api');

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        // First view
        $this->postJson("/api/v1/plugins/{$plugin->id}/view")->assertOk();

        // Simulate expiration by removing from cache manually
        Cache::forget("plugin_view:{$plugin->id}:{$user->id}");

        // Second view after TTL
        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 2);

        $this->assertEquals(2, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_04_guest_first_view(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        $response = $this->withHeaders([
            'User-Agent' => 'TestBrowser 1.0',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 1);

        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_05_guest_view_again_same_fingerprint(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        $headers = [
            'User-Agent' => 'TestBrowser 1.0',
            'REMOTE_ADDR' => '192.168.1.1',
        ];

        // First view
        $this->withHeaders($headers)->postJson("/api/v1/plugins/{$plugin->id}/view")->assertOk();

        // Second view
        $response = $this->withHeaders($headers)->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ignored')
            ->assertJsonPath('data.view_count', 1);

        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_06_two_guests_different_fingerprints(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        // Guest 1
        $this->withHeaders([
            'User-Agent' => 'TestBrowser 1.0',
            'REMOTE_ADDR' => '192.168.1.1',
        ])->postJson("/api/v1/plugins/{$plugin->id}/view")->assertOk();

        // Guest 2 (Different IP)
        $response = $this->withHeaders([
            'User-Agent' => 'TestBrowser 1.0',
            'REMOTE_ADDR' => '192.168.1.2',
        ])->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 2);

        $this->assertEquals(2, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_07_plugin_not_found(): void
    {
        $response = $this->postJson('/api/v1/plugins/99999999-9999-9999-9999-999999999999/view');
        $response->assertNotFound();

        $this->assertFalse(Redis::hget('plugins:views_buffer', '99999999-9999-9999-9999-999999999999'));
    }

    public function test_view_08_plugin_not_approved(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");
        $response->assertNotFound();

        $this->assertFalse(Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_09_redis_buffer_is_null_initially(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'view_count' => 10,
        ]);

        $this->assertFalse(Redis::hget('plugins:views_buffer', $plugin->id));

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 11); // DB(10) + Buffer(1)
    }

    public function test_view_10_db_has_views_and_redis_has_buffer(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'view_count' => 100,
        ]);

        Redis::hset('plugins:views_buffer', $plugin->id, 5);

        $response = $this->postJson("/api/v1/plugins/{$plugin->id}/view");

        // Request will trigger +1 to buffer, so total is DB(100) + Buffer(5+1) = 106
        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.view_count', 106);

        $this->assertEquals(6, Redis::hget('plugins:views_buffer', $plugin->id));
    }

    public function test_view_11_cache_ttl_is_respected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'api');

        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
        ]);

        config()->set('plugins.view_cache_ttl', 3600); // 1 hour

        $this->postJson("/api/v1/plugins/{$plugin->id}/view")->assertOk();

        $cacheKey = "plugin_view:{$plugin->id}:{$user->id}";

        // Assert cache exists
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_view_12_two_different_plugins_viewed_by_same_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'api');

        $pluginA = Plugin::factory()->create(['status' => PluginStatus::Approved]);
        $pluginB = Plugin::factory()->create(['status' => PluginStatus::Approved]);

        $this->postJson("/api/v1/plugins/{$pluginA->id}/view")->assertOk()->assertJsonPath('data.view_count', 1);
        $this->postJson("/api/v1/plugins/{$pluginB->id}/view")->assertOk()->assertJsonPath('data.view_count', 1);

        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $pluginA->id));
        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $pluginB->id));
    }

    public function test_view_13_concurrent_requests_from_same_viewer(): void
    {
        $user = User::factory()->create();

        // The service identifies the viewer via Auth::user(), not the request's
        // user resolver — a bare `new Request` has no route, so the fingerprint
        // fallback would throw. Acting as the user hits the same branch a real
        // authenticated request does.
        Sanctum::actingAs($user, [], 'api');

        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved]);

        $service = app(PluginService::class);

        // Verify Cache::add logic by calling the service method twice in sequence.
        // The second call will hit the Cache::add returning false branch.
        $result1 = $service->incrementViewIfNotViewed($plugin->id, new Request);
        $result2 = $service->incrementViewIfNotViewed($plugin->id, new Request);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('ignored', $result2['status']);

        // Should only increment Redis once
        $this->assertEquals(1, Redis::hget('plugins:views_buffer', $plugin->id));
    }
}
