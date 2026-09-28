<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PluginController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::patch('/user', [ProfileController::class, 'updateProfile']);
        Route::post('/user/avatar', [ProfileController::class, 'updateAvatar']);
        Route::patch('/user/password', [ProfileController::class, 'updatePassword']);

        // ================ Plugin ======================
        Route::post('/plugins', [PluginController::class, 'store'])
            ->middleware('throttle:submit-plugin');
    });

    Route::post('/plugins/{id}/view', [PluginController::class, 'trackView'])
        ->whereUuid('id')
        ->middleware('throttle:60,1');

});
