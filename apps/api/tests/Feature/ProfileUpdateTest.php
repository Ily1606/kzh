<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_profile()
    {
        $response = $this->patchJson('/api/v1/user', ['name' => 'New Name']);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_name()
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['name' => 'Nguyen Van Giap']);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Nguyen Van Giap');
    }

        public function test_authenticated_user_can_remove_avatar_via_upload_api()
    {
        $user = User::factory()->create();
        $user->profile()->create(['id' => (string) Str::uuid(), 'avatar_link' => 'avatars/old.png']);
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/user/avatar', []);
        $response->assertStatus(200)->assertJsonPath('data.avatarLink', null);
    }

    public function test_authenticated_user_can_update_github_name()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['githubName' => 'giapnguyen']);
        $response->assertStatus(200)->assertJsonPath('data.githubName', 'giapnguyen');
    }

    public function test_authenticated_user_can_update_github_link()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['githubLink' => 'https://github.com/giapnguyen']);
        $response->assertStatus(200)->assertJsonPath('data.githubLink', 'https://github.com/giapnguyen');
    }

    public function test_authenticated_user_can_remove_github_link()
    {
        $user = User::factory()->create();
        $user->profile()->create(['id' => (string) Str::uuid(), 'github_link' => 'https://github.com/giapnguyen']);
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['githubLink' => null]);
        $response->assertStatus(200)->assertJsonPath('data.githubLink', null);
    }

    public function test_authenticated_user_can_update_multiple_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', [
            'name' => 'Nguyen Van Giap',
            'githubName' => 'giapnguyen',
            'githubLink' => 'https://github.com/giapnguyen',
        ]);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Nguyen Van Giap');
    }

    public function test_sending_empty_payload_does_not_change_anything()
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', []);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Original Name');
    }

    public function test_validation_fails_if_name_is_not_string()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['name' => 123]);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_if_name_is_too_long()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['name' => Str::random(256)]);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

        public function test_validation_fails_if_github_link_is_invalid_url()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['githubLink' => 'github.com/user']);
        $response->assertStatus(422)->assertJsonValidationErrors(['githubLink']);
    }

    public function test_validation_fails_if_github_name_is_too_long()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['githubName' => Str::random(256)]);
        $response->assertStatus(422)->assertJsonValidationErrors(['githubName']);
    }

    public function test_user_cannot_update_unauthorized_fields()
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);
        $response = $this->patchJson('/api/v1/user', ['is_admin' => true]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => false]);
    }

        public function test_authenticated_user_can_upload_avatar_image()
    {
        $disk = Storage::fake('public');
        $user = User::factory()->create();

        $file = \Illuminate\Http\UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/user/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('avatars/', $response->json('data.avatarLink'));

        // Verify the file was stored
        $path = $response->json('data.avatarLink');
        $disk->assertExists($path);
    }
}
