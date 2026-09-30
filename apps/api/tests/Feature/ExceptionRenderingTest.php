<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ExceptionRenderingTest extends TestCase
{
    public function test_http_response_exception_keeps_its_response(): void
    {
        Route::get('/api/test/http-response', fn () => throw new HttpResponseException(response()->json(['x' => 1], 418)));

        $this->getJson('/api/test/http-response')
            ->assertStatus(418)
            ->assertExactJson(['x' => 1]);
    }

    public function test_throttled_response_keeps_retry_after_header(): void
    {
        Route::get('/api/test/throttled', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => 30]));

        $this->getJson('/api/test/throttled')
            ->assertStatus(429)
            ->assertHeader('Retry-After', 30);
    }

    public function test_http_response_exception_from_middleware_keeps_its_response(): void
    {
        // Route::run() catches HttpResponseException thrown inside a route closure
        // (vendor Route.php:220), so that path never reaches bootstrap/app.php.
        // Throwing from middleware goes through Pipeline::handleException() ->
        // Handler::render() -> the render callbacks in bootstrap/app.php.
        Route::get('/api/test/http-response-middleware', fn () => ['never' => 'reached'])
            ->middleware(ThrowHttpResponseExceptionFromMiddleware::class);

        $this->getJson('/api/test/http-response-middleware')
            ->assertStatus(418)
            ->assertExactJson(['x' => 1]);
    }
}

class ThrowHttpResponseExceptionFromMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        throw new HttpResponseException(response()->json(['x' => 1], 418));
    }
}
