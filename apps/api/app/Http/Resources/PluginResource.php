<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'license' => $this->license,
            'approved_at' => $this->approved_at?->toISOString(),
            'status' => $this->status->value,
            'source_link' => $this->source_link,
            'star_count' => $this->star_count,

            // Viewer-specific: whether the signed-in user starred this plugin.
            // The attribute is only set by the list endpoint for authenticated
            // viewers, so guests (and the trending/submit responses) omit it
            // entirely instead of reporting a misleading `false`.
            'is_star' => $this->when($this->is_star !== null, (bool) $this->is_star),

            'comment_count' => $this->comment_count,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
