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

    public function test_owner_can_open_their_own_pending_plugin(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);

        Sanctum::actingAs($owner, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.id', $plugin->id)
            ->assertJsonPath('data.plugin.status', 'pending');
    }

    public function test_owner_can_open_their_own_rejected_plugin(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Rejected,
            'approved_at' => null,
        ]);

        Sanctum::actingAs($owner, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertOk()
            ->assertJsonPath('data.plugin.status', 'rejected');
    }

    /**
     * The whole point of letting the author in is the author's own plugin. The
     * moment this returns 200 for someone else, an unpublished package is
     * readable by the public.
     */
    public function test_another_user_gets_404_for_a_pending_plugin(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Pending]);
        $stranger = User::factory()->create();

        Sanctum::actingAs($stranger, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertNotFound();
    }

    public function test_another_user_gets_404_for_a_rejected_plugin(): void
    {
        $plugin = Plugin::factory()->create(['status' => PluginStatus::Rejected]);
        $stranger = User::factory()->create();

        Sanctum::actingAs($stranger, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertNotFound();
    }

    /**
     * Author or not, a soft-deleted row is gone. The owner's door does not
     * reach into the trash.
     */
    public function test_soft_deleted_plugin_is_404_even_for_its_owner(): void
    {
        $owner = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Pending,
        ]);
        $plugin->delete();

        Sanctum::actingAs($owner, [], 'api');

        $this->getJson("/api/v1/plugins/{$plugin->id}")
            ->assertNotFound();
    }

    /**
     * The owner's read must be the same read the catalogue serves, apart from
     * the status. `findById` skipping the eager load would answer 200 with
     * `author: null`, and the client's cache merge would then write that null
     * over an author it already had.
     */
    public function test_owner_read_returns_the_same_payload_shape_as_a_public_read(): void
    {
        $owner = User::factory()->create(['name' => 'Jane Dev']);
        $plugin = Plugin::factory()->create([
            'user_id' => $owner->id,
            'status' => PluginStatus::Pending,
            'approved_at' => null,
        ]);

        Sanctum::actingAs($owner, [], 'api');

        $response = $this->getJson("/api/v1/plugins/{$plugin->id}")->assertOk();

        $response->assertJsonStructure([
            'data' => ['plugin' => [
                'id', 'name', 'user_id', 'author' => ['id', 'name', 'avatar_url'],
                'title', 'license', 'approved_at', 'status', 'source_link',
                'star_count', 'comment_count', 'view_count', 'created_at', 'updated_at',
            ]],
        ]);

        $response->assertJsonPath('data.plugin.author.name', 'Jane Dev');
    }
}
