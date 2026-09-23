<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiMessage;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::successResponse(
            ['status' => 'ok'],
            ApiMessage::HEALTH_CHECK_SUCCESSFUL->value,
        );
    }
}
