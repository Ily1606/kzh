<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // relationLoaded check the User object associated with this specific Plugin already in RAM?

        /** @var User|null $author */
        $author = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'user_id' => $this->user_id,
            'author' => $author ? [
                'id' => $author->id,
                'name' => $author->name,
                'avatar_url' => $author->profile?->avatar_url,
            ] : null,
            'title' => $this->title,
            'license' => $this->license,
            'approved_at' => $this->approved_at?->toISOString(),
            'status' => $this->status->value,
            'source_link' => $this->source_link,
            'star_count' => (int) ($this->star_count ?? $this->stars()->count()),
            'is_star' => $this->when($this->is_star !== null, (bool) $this->is_star),
            'comment_count' => (int) ($this->comment_count ?? $this->comments()->visible()->count()),
            'view_count' => $this->view_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
