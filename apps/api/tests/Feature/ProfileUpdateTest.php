<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

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
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['name' => 'Nguyen Van Giap']);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Nguyen Van Giap');
    }

    public function test_authenticated_user_can_update_avatar()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['avatarLink' => 'https://example.com/avatar.png']);
        $response->assertStatus(200)->assertJsonPath('data.avatarLink', 'https://example.com/avatar.png');
    }

    public function test_authenticated_user_can_remove_avatar()
    {
        $user = User::factory()->create(['avatarLink' => 'https://example.com/avatar.png']);
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['avatarLink' => null]);
        $response->assertStatus(200)->assertJsonPath('data.avatarLink', null);
    }

    public function test_authenticated_user_can_update_github_name()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['githubName' => 'giapnguyen']);
        $response->assertStatus(200)->assertJsonPath('data.githubName', 'giapnguyen');
    }

    public function test_authenticated_user_can_update_github_link()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['githubLink' => 'https://github.com/giapnguyen']);
        $response->assertStatus(200)->assertJsonPath('data.githubLink', 'https://github.com/giapnguyen');
    }

    public function test_authenticated_user_can_remove_github_link()
    {
        $user = User::factory()->create(['githubLink' => 'https://github.com/giapnguyen']);
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['githubLink' => null]);
        $response->assertStatus(200)->assertJsonPath('data.githubLink', null);
    }

    public function test_authenticated_user_can_update_multiple_fields()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', [
            'name' => 'Nguyen Van Giap',
            'avatarLink' => 'https://example.com/avatar.jpg',
            'githubName' => 'giapnguyen',
            'githubLink' => 'https://github.com/giapnguyen',
        ]);
        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Nguyen Van Giap')
                 ->assertJsonPath('data.avatarLink', 'https://example.com/avatar.jpg');
    }

    public function test_sending_empty_payload_does_not_change_anything()
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        $response = $this->actingAs($user)->patchJson('/api/v1/user', []);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Original Name');
    }

    public function test_validation_fails_if_name_is_not_string()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['name' => 123]);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_if_name_is_too_long()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['name' => Str::random(256)]);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_if_avatar_link_is_invalid_url()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['avatarLink' => 'abc']);
        $response->assertStatus(422)->assertJsonValidationErrors(['avatarLink']);
    }

    public function test_validation_fails_if_github_link_is_invalid_url()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['githubLink' => 'github.com/user']);
        $response->assertStatus(422)->assertJsonValidationErrors(['githubLink']);
    }

    public function test_validation_fails_if_github_name_is_too_long()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['githubName' => Str::random(256)]);
        $response->assertStatus(422)->assertJsonValidationErrors(['githubName']);
    }

    public function test_user_cannot_update_unauthorized_fields()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $response = $this->actingAs($user)->patchJson('/api/v1/user', ['is_admin' => true]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => false]);
    }

        public function test_authenticated_user_can_upload_avatar_image()
    {
        $disk = Storage::fake('public');
        $user = User::factory()->create();

        $file = \Illuminate\Http\UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/v1/user/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('storage/avatars/', $response->json('data.avatarLink'));

        // Verify the file was stored
        $path = str_replace(config('app.url') . '/storage/', '', $response->json('data.avatarLink'));
        $disk->assertExists($path);
    }
}
