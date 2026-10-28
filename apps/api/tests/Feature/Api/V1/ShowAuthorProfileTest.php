<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowAuthorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_public_profile(): void
    {
        $author = User::factory()->create([
            'name' => 'Ada Lovelace',
            'username' => 'ada',
        ]);

        $author->profile()->create([
            'avatar_link' => 'https://example.com/avatar.png',
            'github_link' => 'https://github.com/ada',
        ]);

        $response = $this->getJson('/api/v1/users/ada');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $author->id)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.username', 'ada')
            ->assertJsonPath('data.avatar_url', 'https://example.com/avatar.png')
            ->assertJsonPath('data.github_link', 'https://github.com/ada');
    }

    public function test_does_not_expose_email_or_admin_flag(): void
    {
        User::factory()->create(['username' => 'ada']);

        $response = $this->getJson('/api/v1/users/ada');

        $response->assertOk()
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.is_admin')
            ->assertJsonMissingPath('data.password');
    }

    public function test_returns_the_default_avatar_when_the_author_has_no_profile(): void
    {
        User::factory()->create(['username' => 'ada']);

        $response = $this->getJson('/api/v1/users/ada');

        $response->assertOk()
            ->assertJsonPath('data.avatar_url', url(UserProfile::DEFAULT_AVATAR_PATH))
            ->assertJsonPath('data.github_link', null);
    }

    public function test_finds_the_author_regardless_of_case(): void
    {
        User::factory()->create(['username' => 'ada']);

        $this->getJson('/api/v1/users/ADA')->assertOk()
            ->assertJsonPath('data.username', 'ada');
    }

    public function test_returns_404_for_an_unknown_username(): void
    {
        $this->getJson('/api/v1/users/nobody')->assertNotFound();
    }

    public function test_returns_404_for_a_malformed_username(): void
    {
        $this->getJson('/api/v1/users/not_a_valid_name')->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));
        $this->getJson('/api/v1/users/-leading-dash')->assertNotFound();
        $this->getJson('/api/v1/users/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_returns_404_for_a_soft_deleted_author(): void
    {
        $author = User::factory()->create(['username' => 'ada']);
        $author->delete();

        $this->getJson('/api/v1/users/ada')->assertNotFound();
    }

    public function test_lists_only_the_published_plugins_of_that_author(): void
    {
        $author = User::factory()->create(['username' => 'ada']);

        $published = Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Pending,
        ]);

        Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Rejected,
        ]);

        $response = $this->getJson('/api/v1/users/ada/plugins');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_does_not_list_the_plugins_of_another_author(): void
    {
        $author = User::factory()->create(['username' => 'ada']);

        Plugin::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->getJson('/api/v1/users/ada/plugins')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_sorts_the_published_plugins_by_approved_at_desc(): void
    {
        $author = User::factory()->create(['username' => 'ada']);

        $older = Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDays(2),
        ]);

        $newer = Plugin::factory()->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/users/ada/plugins');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_paginates_the_published_plugins(): void
    {
        $author = User::factory()->create(['username' => 'ada']);

        Plugin::factory()->count(5)->create([
            'user_id' => $author->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/users/ada/plugins?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_returns_404_for_the_plugins_of_an_unknown_author(): void
    {
        $this->getJson('/api/v1/users/nobody/plugins')->assertNotFound();
    }

    public function test_can_show_admin_profile_and_plugins(): void
    {
        $admin = User::factory()->create(['username' => 'ada', 'is_admin' => true]);

        $plugin = Plugin::factory()->create([
            'user_id' => $admin->id,
            'status' => PluginStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->getJson('/api/v1/users/ada')->assertOk()
            ->assertJsonPath('data.username', 'ada');

        $this->getJson('/api/v1/users/ada/plugins')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plugin->id);
    }
}
