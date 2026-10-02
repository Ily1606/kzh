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
            // Number of visible direct replies — the client renders a "View N
            // replies" affordance when this is above 0 and calls the replies
            // endpoint to expand them. The list queries compute it per row, so it
            // is null on the create path, where a brand-new comment cannot have
            // replies yet; the 0 fallback keeps that payload an integer too.
            'replies_count' => $this->visible_replies_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
