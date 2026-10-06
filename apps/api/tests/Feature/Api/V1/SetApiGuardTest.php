<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SetApiGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_api_guard_middleware_sets_the_api_guard(): void
    {
        $user = User::factory()->create();

        // Act: Request goes through the SetApiGuard middleware
        $response = $this->withHeader('Authorization', 'Bearer '.$this->getValidSanctumToken($user))
            ->getJson('/api/v1/plugins');

        // Assert: The middleware should set the guard to 'api'
        // Auth::user() should resolve the authenticated user via the api guard
        $this->assertNotNull(Auth::user());
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_set_api_guard_middleware_does_not_require_authentication(): void
    {
        // Act: Request without authentication goes through the SetApiGuard middleware
        $response = $this->getJson('/api/v1/plugins');

        // Assert: Should return OK without requiring auth (guard set to api)
        $response->assertOk();
    }

    public function test_set_api_guard_with_authenticated_user(): void
    {
        $user = User::factory()->create();

        // Act: Request with valid Sanctum token
        $response = $this->withHeader('Authorization', 'Bearer '.$this->getValidSanctumToken($user))
            ->getJson('/api/v1/plugins');

        // Assert: User should be resolved by Auth::user()
        $this->assertNotNull(Auth::user());
        $this->assertEquals($user->id, Auth::id());
    }

    /**
     * Get a valid Sanctum token for a user
     */
    private function getValidSanctumToken(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }
}
