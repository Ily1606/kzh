<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route chỉ tồn tại trong test để quan sát IP mà request nhìn thấy.
        Route::middleware('api')->get('/_test/client-ip', function (Request $request) {
            return response()->json(['ip' => $request->ip()]);
        });
    }

    public function test_x_forwarded_for_is_ignored_when_no_proxy_is_trusted(): void
    {
        config()->set('trustedproxy.proxies', null);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.99')
            ->getJson('/_test/client-ip')
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.7');
    }

    public function test_x_forwarded_for_is_used_when_the_connecting_ip_is_a_trusted_proxy(): void
    {
        config()->set('trustedproxy.proxies', '198.51.100.0/24');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.99')
            ->getJson('/_test/client-ip')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.99');
    }

    public function test_forged_x_forwarded_for_cannot_bypass_the_auth_rate_limiter(): void
    {
        config()->set('trustedproxy.proxies', null);

        $limit = (int) config('rate_limits.auth_per_minute');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
                ->withHeader('X-Forwarded-For', "203.0.113.{$attempt}")
                ->postJson('/api/v1/login', [
                    'email' => 'missing@example.com',
                    'password' => 'wrong-password',
                ])->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.250')
            ->postJson('/api/v1/login', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertTooManyRequests();
    }
}
