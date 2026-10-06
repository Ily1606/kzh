<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitCommentReportRequest;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Plugin;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CommentReportController extends Controller
{
    /**
     * Submit a report for a comment.
     *
     * Allows a user to report an inappropriate or spam comment.
     * Users can only report a specific comment once.
     *
     * @tags Comments
     */
    public function store(SubmitCommentReportRequest $request, string $pluginId, string $commentId): JsonResponse
    {
        $plugin = Plugin::findOrFail($pluginId);
        $comment = Comment::where('plugin_id', $plugin->id)->findOrFail($commentId);

        $userId = $request->user()->id;

        // Check if the user has already reported this comment
        $existingReport = CommentReport::where('user_id', $userId)
            ->where('comment_id', $comment->id)
            ->exists();

        if ($existingReport) {
            return ApiResponse::errorResponse('You have already reported this comment.', 409);
        }

        $report = new CommentReport();
        $report->user_id = $userId;
        $report->comment_id = $comment->id;
        $report->plugin_id = $plugin->id;
        $report->reason = $request->validated('reason');
        $report->save();

        return ApiResponse::successResponse(
            ['report' => $report],
            'Comment reported successfully. Our moderators will review it shortly.',
            201
        );
    }
}
