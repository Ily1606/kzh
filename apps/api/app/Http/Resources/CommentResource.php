<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $author */
        $author = $this->relationLoaded('author') ? $this->author : null;

        return [
            'id' => $this->id,
            'plugin_id' => $this->plugin_id,
            'parent_comment_id' => $this->parent_comment_id,
            'content' => $this->content,
            'author' => $author ? [
                'id' => $author->id,
                'name' => $author->name,
                'avatar_url' => $author->profile?->avatar_url,
            ] : null,
            // Number of visible direct replies. The client renders a
            // "View N replies" affordance when this is greater than 0 and calls
            // the replies endpoint to expand them.
            'replies_count' => $this->visible_replies_count ?? $this->replies_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
