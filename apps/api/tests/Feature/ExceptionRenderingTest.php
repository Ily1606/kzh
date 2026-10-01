<?php

namespace Tests\Feature;

use App\Models\Comment;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    // -----------------------------------------------------------------------
    // HttpExceptionInterface message mapping
    //
    // The renderer in bootstrap/app.php picks the message by status and never
    // echoes $exception->getMessage(). Each status therefore needs its own
    // test: one that is not mapped silently falls through to the generic
    // default, so the client reads "The request could not be completed." for
    // failures that deserve a more specific word.
    // -----------------------------------------------------------------------

    public function test_404_maps_to_the_not_found_message(): void
    {
        Route::get('/api/test/not-found', fn () => throw new HttpException(404, 'Route /api/test/not-found not found.'));

        $this->getJson('/api/test/not-found')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.not_found'))
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors', null);
    }

    public function test_429_maps_to_the_too_many_requests_message(): void
    {
        Route::get('/api/test/rate-limited', fn () => throw new ThrottleRequestsException('Too Many Attempts.'));

        $response = $this->getJson('/api/test/rate-limited')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.too_many_requests'))
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors', null);

        // The exception carries its own text; the client must not see it, and
        // must not see the generic default either.
        $this->assertStringNotContainsString('Too Many Attempts.', $response->getContent());
    }

    /**
     * The 429 a client actually meets comes from the `throttle:*` middleware,
     * not from a route closure, and it arrives with the retry advice already
     * attached. Both the header and the translated message have to survive.
     */
    public function test_429_from_throttle_middleware_keeps_retry_after_and_translated_message(): void
    {
        Route::get('/api/test/throttle-middleware', fn () => ['never' => 'reached'])
            ->middleware('throttle:1,1');

        $this->getJson('/api/test/throttle-middleware')->assertOk();

        $this->getJson('/api/test/throttle-middleware')
            ->assertStatus(429)
            ->assertJsonPath('message', __('api.too_many_requests'))
            ->assertHeader('Retry-After');
    }

    public function test_403_maps_to_the_unauthorized_message(): void
    {
        Route::get('/api/test/forbidden', fn () => throw new HttpException(403, 'This action is unauthorized.'));

        $this->getJson('/api/test/forbidden')
            ->assertForbidden()
            ->assertJsonPath('message', __('api.unauthorized'));
    }

    public function test_419_maps_to_the_invalid_token_message(): void
    {
        Route::get('/api/test/expired', fn () => throw new HttpException(419, 'Page Expired'));

        $this->getJson('/api/test/expired')
            ->assertStatus(419)
            ->assertJsonPath('message', __('api.invalid_token'));
    }

    /**
     * Anything not on the list gets the generic message on purpose: an unmapped
     * status must not fall back to the exception text, which is where framework
     * and driver internals would leak from.
     */
    public function test_unmapped_status_falls_back_to_the_generic_message(): void
    {
        Route::get('/api/test/teapot', fn () => throw new HttpException(418, 'I am a teapot with a very specific reason.'));

        $response = $this->getJson('/api/test/teapot')
            ->assertStatus(418)
            ->assertJsonPath('message', __('api.request_failed'));

        $this->assertStringNotContainsString('teapot', $response->getContent());
    }

    /**
     * `ModelNotFoundException` is not an HttpExceptionInterface — the handler
     * converts it to a 404 before rendering. The conversion keeps the
     * framework's message, "No query results for model [App\Models\Comment]
     * {uuid}", and that message names the model class and the id that failed to
     * resolve, so the renderer still has to replace it.
     */
    public function test_model_not_found_exception_does_not_leak_the_model_class_or_id(): void
    {
        $missingId = '00000000-0000-0000-0000-000000000000';

        Route::get('/api/test/model-not-found', function () use ($missingId): never {
            throw (new ModelNotFoundException)->setModel(Comment::class, [$missingId]);
        });

        $response = $this->getJson('/api/test/model-not-found')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('api.not_found'));

        $this->assertStringNotContainsString(Comment::class, $response->getContent());
        $this->assertStringNotContainsString($missingId, $response->getContent());
    }

    /**
     * A message attached on purpose via `abort(4xx, '...')` is not
     * client-facing either: the status decides the message, so a hand-written
     * string cannot leak internals the way a framework message can.
     */
    public function test_custom_exception_message_is_not_echoed_to_the_client(): void
    {
        Route::get('/api/test/custom-message', fn () => abort(404, 'Comment belongs to another plugin.'));

        $response = $this->getJson('/api/test/custom-message')
            ->assertNotFound()
            ->assertJsonPath('message', __('api.not_found'));

        $this->assertStringNotContainsString('another plugin', $response->getContent());
    }
}

class ThrowHttpResponseExceptionFromMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        throw new HttpResponseException(response()->json(['x' => 1], 418));
    }
}
