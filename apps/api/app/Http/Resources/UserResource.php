<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatarLink' => $this->profile?->avatar_link,
            'githubName' => $this->profile?->github_name,
            'githubLink' => $this->profile?->github_link,
            'is_admin' => $this->is_admin,
            'created_at' => $this->created_at,
        ];
    }
}
