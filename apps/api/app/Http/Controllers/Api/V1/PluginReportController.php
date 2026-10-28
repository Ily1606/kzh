<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginReportRequest;
use App\Models\PluginReport;
use App\Services\PluginService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

class PluginReportController extends Controller
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    /**
     * Submit a report for a plugin.
     *
     * Allows a user to report a plugin for inappropriate content, policy violations, or malware.
     * Users can only report a specific plugin once.
     *
     * @tags Plugins
     */
    #[Response(status: 403, description: 'You cannot report your own plugin.')]
    #[Response(status: 404, description: 'The plugin does not exist, is soft-deleted, or is not approved and not the caller\'s.')]
    #[Response(status: 409, description: 'You have already reported this plugin.')]
    public function store(SubmitPluginReportRequest $request, string $pluginId): JsonResponse
    {
        $plugin = $this->pluginService->getPlugin($pluginId, $request->user());
        $userId = $request->user()->id;

        // An author cannot report their own plugin
        if ($plugin->user_id === $userId) {
            return ApiResponse::errorResponse('You cannot report your own plugin.', 403);
        }

        // Check if the user has already reported this plugin (including previously dismissed reports)
        $existingReport = PluginReport::withTrashed()
            ->where('user_id', $userId)
            ->where('plugin_id', $plugin->id)
            ->exists();

        if ($existingReport) {
            return ApiResponse::errorResponse('You have already reported this plugin.', 409);
        }

        $report = new PluginReport;
        $report->user_id = $userId;
        $report->plugin_id = $plugin->id;
        $report->reason = $request->validated('reason');

        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            return ApiResponse::errorResponse('You have already reported this plugin.', 409);
        }

        return ApiResponse::successResponse(
            ['report' => $report],
            'Plugin reported successfully. Our moderators will review it shortly.',
            201
        );
    }
}
