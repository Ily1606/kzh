<?php

namespace Tests\Feature;

use App\Models\User;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_docs_ui_is_available_and_spec_only_contains_v1_routes(): void
    {
        Gate::define('viewApiDocs', fn (?User $user) => true);

        $this->assertContains(RestrictedDocsAccess::class, config('scramble.middleware'));

        Route::get('/api/v2/internal', fn () => ['status' => 'internal']);

        $this->get('/docs/api')->assertOk();

        $paths = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json('paths');

        $this->assertArrayHasKey('/v1/health', $paths);
        $this->assertArrayHasKey('/v1/plugins', $paths);
        $this->assertArrayNotHasKey('/v2/internal', $paths);
    }

    /**
     * A throttled endpoint that does not document its 429 reads to a client as
     * "this endpoint can never fail", and the retry advice is exactly what a
     * caller needs while it is being rate limited. Scramble cannot infer it:
     * the response comes from middleware, not from the return type.
     */
    public function test_throttled_endpoints_document_their_429_response(): void
    {
        Gate::define('viewApiDocs', fn (?User $user) => true);

        $paths = $this->getJson('/docs/api.json')
            ->assertOk()
            ->json('paths');

        $throttled = [
            '/v1/register' => 'post',
            '/v1/login' => 'post',
            '/v1/forgot-password' => 'post',
            '/v1/reset-password' => 'post',
            '/v1/plugins' => 'post',
        ];

        foreach ($throttled as $path => $method) {
            $this->assertArrayHasKey(
                '429',
                $paths[$path][$method]['responses'] ?? [],
                "{$method} {$path} is throttled but does not document a 429 response.",
            );
        }
    }
}
