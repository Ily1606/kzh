<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset-link');
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:password-reset');
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::patch('/user', [ProfileController::class, 'updateProfile']);
        Route::post('/user/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:strict');
        Route::patch('/user/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:strict');

        // ================ Plugin ======================
        Route::post('/plugins', [PluginController::class, 'store'])
            ->middleware('throttle:submit-plugin');
    });

    Route::middleware('throttle:api')->group(function () {
        Route::get('/plugins', [PluginController::class, 'index']);
        Route::get('/plugins/trending', [PluginController::class, 'trending']);
        Route::post('/plugins/{id}/view', [PluginController::class, 'trackView'])
            ->whereUuid('id');
    });
});
