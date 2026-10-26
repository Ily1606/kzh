<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitPluginReportRequest;
use App\Models\Plugin;
use App\Models\PluginReport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PluginReportController extends Controller
{
    /**
     * Submit a report for a plugin.
     *
     * Allows a user to report a plugin for inappropriate content, policy violations, or malware.
     * Users can only report a specific plugin once.
     *
     * @tags Plugins
     */
    public function store(SubmitPluginReportRequest $request, string $pluginId): JsonResponse
    {
        $plugin = Plugin::findOrFail($pluginId);
        $userId = $request->user()->id;

        // Check if the user has already reported this plugin
        $existingReport = PluginReport::where('user_id', $userId)
            ->where('plugin_id', $plugin->id)
            ->exists();

        if ($existingReport) {
            return ApiResponse::errorResponse('You have already reported this plugin.', 409);
        }

        $report = new PluginReport();
        $report->user_id = $userId;
        $report->plugin_id = $plugin->id;
        $report->reason = $request->validated('reason');
        $report->save();

        return ApiResponse::successResponse(
            ['report' => $report],
            'Plugin reported successfully. Our moderators will review it shortly.',
            201
        );
    }
}

