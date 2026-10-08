<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowPluginTest extends TestCase
{
    use RefreshDatabase;

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

    private function givenApprovedPlugin(): Plugin
    {
        return Plugin::factory()->create([
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function test_returns_the_plugin(): void
    {
        $plugin = $this->givenApprovedPlugin();

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.id', $plugin->id)
            ->assertJsonPath('data.plugin.name', $plugin->name);
    }

    public function test_marks_is_star_true_for_the_plugin_the_user_starred(): void
    {
        $user = User::factory()->create();
        $plugin = $this->givenApprovedPlugin();

        $this->givenStarred($plugin, $user);

        Sanctum::actingAs($user, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.is_star', true);
    }

    public function test_marks_is_star_false_when_another_user_starred_it(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $plugin = $this->givenApprovedPlugin();
        $this->givenStarred($plugin, $owner);

        Sanctum::actingAs($other, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.is_star', false);
    }

    public function test_is_star_is_scoped_to_the_current_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $plugin = $this->givenApprovedPlugin();
        $this->givenStarred($plugin, $first);

        Sanctum::actingAs($second, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.is_star', false);
    }

    public function test_guest_does_not_receive_the_is_star_field(): void
    {
        $plugin = $this->givenApprovedPlugin();

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.plugin.is_star');
    }

    public function test_returns_404_for_an_unknown_plugin(): void
    {
        $this->getJson('/api/v1/plugins/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_returns_404_for_a_plugin_that_is_not_approved(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Pending,
        ]);

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertNotFound();
    }
}
