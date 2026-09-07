<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AppPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppPublication
 */
class PublicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'welcome_message' => $this->welcome_message,
            'suggested_questions' => $this->suggested_questions ?? [],
            'daily_query_limit' => $this->daily_query_limit,
            'employees_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
