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
            'comment_count' => $this->comment_count,
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
