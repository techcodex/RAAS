<?php

namespace App\Http\Resources\Api\App;

use App\Models\AppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppMessage
 */
class AppMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->app_conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
