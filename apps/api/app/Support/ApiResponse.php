<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    public static function successResponse(
        mixed $data = null,
        string $message = 'Request successful.',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ];

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Flatten a paginator into the `meta` argument of successResponse().
     *
     * Lives here rather than in a controller because the `meta` slot is part of
     * the envelope this class owns: keeping the mapping in one place means every
     * paginated endpoint reports page state identically, and adding one later
     * is a single edit instead of a per-controller copy.
     *
     * `from`/`to` are null on an empty page, matching Laravel's paginator.
     *
     * @return array<string, int|null>
     */
    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    public static function errorResponse(
        string $message,
        int $status,
        mixed $errors = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
