<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StarController;
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
        Route::get('/user/plugins', [PluginController::class, 'myPlugins']);

        // ================ Plugin ======================
        Route::prefix('plugins')
            ->controller(PluginController::class)
            ->group(function () {
                Route::post('/', 'store')
                    ->middleware('throttle:submit-plugin');

                Route::patch('/{pluginId}', 'update')
                    ->whereUuid('pluginId');
            });

        // ================ Comment ====================
        Route::post('/plugins/{pluginId}/comments', [CommentController::class, 'store'])
            ->whereUuid('pluginId')
            ->middleware('throttle:create-comment');

        // ================ Star ======================
        Route::post('/plugins/{pluginId}/star', [StarController::class, 'store'])
            ->whereUuid('pluginId')
            ->middleware('throttle:star-plugin');
    });

    Route::middleware(['set.api.guard', 'throttle:api'])->group(function () {
        Route::get('/plugins', [PluginController::class, 'index']);
        Route::get('/plugins/trending', [PluginController::class, 'trending']);
        Route::get('/plugins/{id}', [PluginController::class, 'show'])->whereUuid('id');
        Route::post('/plugins/{id}/view', [PluginController::class, 'trackView'])
            ->whereUuid('id');

        // ================ Comment ====================
        Route::get('/plugins/{pluginId}/comments', [CommentController::class, 'index'])
            ->whereUuid('pluginId');
        Route::get('/plugins/{pluginId}/comments/{commentId}/replies', [CommentController::class, 'replies'])
            ->whereUuid('pluginId')
            ->whereUuid('commentId');
            
        // ================ Star ======================
        Route::get('/plugins/{pluginId}/stars/timeline', [StarController::class, 'timeline'])
            ->whereUuid('pluginId');
    });
});
