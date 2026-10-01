<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPluginsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_paginated_approved_plugins_sorted_by_approved_at_desc(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Pending,
        ]);

        $olderPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(2),
        ]);

        $newerPlugin = Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/plugins');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerPlugin->id)
            ->assertJsonPath('data.1.id', $olderPlugin->id)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'title', 'status', 'approved_at'],
                ],
                'meta' => [
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_does_not_return_approved_plugins_without_approved_at(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => null,
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_returns_empty_when_no_approved_plugins(): void
    {
        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_respects_per_page_parameter(): void
    {
        Plugin::factory()->count(10)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=3');
        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.pagination.per_page', 3)
            ->assertJsonPath('meta.pagination.total', 10);
    }

    public function test_caps_per_page_at_config_max(): void
    {
        config()->set('plugins.pagination.max_per_page', 5);

        Plugin::factory()->count(10)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=50');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_uses_default_per_page_when_not_specified(): void
    {
        config()->set('plugins.pagination.default_per_page', 2);

        Plugin::factory()->count(5)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2);
    }

    public function test_pagination_navigates_correctly(): void
    {
        Plugin::factory()->count(5)->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins?per_page=2&page=2');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 3);
    }

    public function test_response_uses_plugin_resource_format(): void
    {
        Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/plugins');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'user_id',
                        'title',
                        'license',
                        'approved_at',
                        'status',
                        'source_link',
                        'star_count',
                        'comment_count',
                        'view_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $response->assertJsonMissingPath('data.0.deleted_at');
    }
}
