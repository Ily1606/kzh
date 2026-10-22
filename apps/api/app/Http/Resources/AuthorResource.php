<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'github_link' => $this->profile?->github_link,
            'avatar_url' => $this->profile?->avatar_url,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
