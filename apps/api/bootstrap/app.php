<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use App\Enums\ApiMessage;
use App\Support\ApiResponse;
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
        $middleware->statefulApi();
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
                ApiMessage::INVALID_DATA->value,
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(ApiMessage::UNAUTHENTICATED->value, 401);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage() ?: match ($status) {
                403 => ApiMessage::UNAUTHORIZED->value,
                404 => ApiMessage::NOT_FOUND->value,
                419 => ApiMessage::INVALID_TOKEN->value,
                default => ApiMessage::REQUEST_FAILED->value,
            };

            return ApiResponse::errorResponse($message, $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::errorResponse(ApiMessage::UNEXPECTED_ERROR->value, 500);
        });
    })->create();
