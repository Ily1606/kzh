<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetPluginStarTest extends TestCase
{
    use RefreshDatabase;

    private function approvedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    /**
     * `stars` has no Eloquent model (composite primary key), so there is no
     * StarFactory to seed through. The table is written to directly instead.
     */
    private function givenStarred(Plugin $plugin, User $user): void
    {
        DB::table('stars')->insert([
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
        ]);
    }

    private function getStar(Plugin $plugin): TestResponse
    {
        return $this->getJson("/api/v1/plugins/{$plugin->id}/star");
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function test_returns_false_when_the_user_has_not_starred_the_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->getStar($plugin)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.starred', false);
    }

    public function test_returns_true_when_the_user_has_starred_the_plugin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $plugin = $this->approvedPlugin();
        $this->givenStarred($plugin, $user);

        $this->getStar($plugin)
            ->assertOk()
            ->assertJsonPath('data.starred', true);
    }

    public function test_is_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($owner);
        $plugin = $this->approvedPlugin();
        $this->givenStarred($plugin, $owner);

        Sanctum::actingAs($other);

        $this->getStar($plugin)->assertOk()->assertJsonPath('data.starred', false);
    }

    // -----------------------------------------------------------------------
    // Contract
    // -----------------------------------------------------------------------

    /**
     * This endpoint answers "did *I* star this", nothing more. `star_count`
     * already ships in PluginResource, and putting it here would invite a client
     * to read a number that was never scoped to the signed-in user.
     */
    public function test_response_does_not_include_the_star_count(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();

        $this->getStar($plugin)
            ->assertOk()
            ->assertJsonMissingPath('data.star_count');
    }

    // -----------------------------------------------------------------------
    // Guards
    // -----------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $plugin = $this->approvedPlugin();

        $this->getStar($plugin)->assertUnauthorized();
    }

    public function test_returns_404_for_a_non_existent_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/plugins/'.fake()->uuid().'/star')->assertNotFound();
    }

    public function test_returns_404_for_a_pending_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);

        $this->getStar($plugin)->assertNotFound();
    }

    public function test_returns_404_for_a_soft_deleted_plugin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $plugin = $this->approvedPlugin();
        $plugin->delete();

        $this->getStar($plugin)->assertNotFound();
    }
}
