<?php

namespace Tests\Feature\Api\V1;

use App\Models\Plugin;
use App\Enums\PluginStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SyncPluginViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_syncs_views_and_clears_buffer()
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Approved, 'view_count' => 10]);
        $bufferKey = config('plugins.views_buffer_key');
        
        Redis::hset($bufferKey, $plugin->id, 5);

        $this->artisan('plugins:sync-views')
            ->expectsOutputToContain('Syncing views for 1 plugins...')
            ->assertSuccessful();

        $this->assertEquals(15, $plugin->fresh()->view_count);
        $this->assertFalse((bool) Redis::exists($bufferKey));
        $this->assertFalse((bool) Redis::exists($bufferKey . '_processing'));
    }

    public function test_handles_leftover_processing_key()
    {
        $plugin1 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'view_count' => 10]);
        $plugin2 = Plugin::factory()->create(['status' => PluginStatus::Approved, 'view_count' => 20]);
        
        $bufferKey = config('plugins.views_buffer_key');
        $processingKey = $bufferKey . '_processing';
        
        // Simulate a crashed previous run leaving data in processing key
        Redis::hset($processingKey, $plugin1->id, 3);
        
        // New data in buffer key
        Redis::hset($bufferKey, $plugin2->id, 4);

        $this->artisan('plugins:sync-views')->assertSuccessful();

        $this->assertEquals(13, $plugin1->fresh()->view_count);
        $this->assertEquals(24, $plugin2->fresh()->view_count);
        
        $this->assertFalse((bool) Redis::exists($bufferKey));
        $this->assertFalse((bool) Redis::exists($processingKey));
    }
}
