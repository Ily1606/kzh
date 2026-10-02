<?php

namespace App\Http\Resources;

use App\DTOs\PluginViewResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluginViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PluginViewResult $this */
        return [
            'status' => $this->counted ? 'success' : 'ignored',
            'view_count' => $this->viewCount,
        ];
    }
}
