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
        $this->assertArrayNotHasKey('/v2/internal', $paths);
    }
}
