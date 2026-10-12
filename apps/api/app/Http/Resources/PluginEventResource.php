<?php

namespace App\Http\Resources;

use App\Models\PluginEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PluginEvent
 */
class PluginEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,

            // Null on `created` and `resubmitted`
            'message' => $this->message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
