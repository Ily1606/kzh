<?php

use App\Exceptions\InvalidCredentialsException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api();
        // Không truyền `at:` ở đây. TrustProxies đã là global middleware
        // nên nó sẽ tự đọc config('trustedproxy.proxies') lúc runtime
        // (request đi qua mới đọc, khi đó config đã load xong).
        // Lý do không gọi config()/env() tại đây: closure này chạy ngay khi
        // resolve ConsoleKernel, lúc đó container chưa có `config` -> crash.
        // Nguồn sự thật duy nhất: config/trustedproxy.php + TRUSTED_PROXIES.
        $middleware->trustProxies();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(
                __('api.invalid_data'),
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(__('api.unauthenticated'), 401);
        });

        $exceptions->render(function (InvalidCredentialsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(__('api.invalid_credentials'), 422, [
                'email' => [__('api.invalid_credentials')],
            ]);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage() ?: match ($status) {
                403 => __('api.unauthorized'),
                404 => __('api.not_found'),
                419 => __('api.invalid_token'),
                default => __('api.request_failed'),
            };

            return ApiResponse::errorResponse($message, $status)
                ->withHeaders($exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpResponseException) {
                // Let Laravel return the wrapped response as-is instead of converting it to a 500.
                return null;
            }

            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(__('api.unexpected_error'), 500);
        });
    })->create();
