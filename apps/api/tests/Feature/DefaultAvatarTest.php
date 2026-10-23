<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DefaultAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_avatar_image_exists_in_the_public_web_root(): void
    {
        $path = public_path(UserProfile::DEFAULT_AVATAR_PATH);

        $this->assertFileExists($path);
        $this->assertSame('image/jpeg', mime_content_type($path));
    }

    public function test_a_user_without_an_avatar_falls_back_to_the_default_avatar(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->profile);
        $this->assertSame(url(UserProfile::DEFAULT_AVATAR_PATH), $user->avatar_url);
    }

    public function test_a_user_with_an_uploaded_avatar_keeps_their_avatar(): void
    {
        Storage::fake(UserProfile::AVATAR_DISK);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/avatar', [
            'avatar' => UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(200);

        $avatarLink = (string) $response->json('data.avatarLink');
        $this->assertStringContainsString('avatars/', $avatarLink);
        $this->assertSame($avatarLink, $user->fresh()->avatar_url);
    }

    public function test_an_external_avatar_link_is_returned_as_is(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['avatar_link' => 'https://example.com/avatar.png']);

        $this->assertSame('https://example.com/avatar.png', $user->fresh()->avatar_url);
    }

    public function test_the_admin_panel_avatar_uses_the_same_default_avatar(): void
    {
        $user = User::factory()->create();

        $this->assertSame(url(UserProfile::DEFAULT_AVATAR_PATH), Filament::getUserAvatarUrl($user->fresh()));
    }
}
