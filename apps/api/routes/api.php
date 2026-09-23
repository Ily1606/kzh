<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Password Reset
    Route::post('/forgot-password', [\App\Http\Controllers\Api\V1\PasswordResetController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [\App\Http\Controllers\Api\V1\PasswordResetController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::patch('/user', [AuthController::class, 'updateProfile']);
        Route::patch('/user/password', [AuthController::class, 'updatePassword']);
    });
});
