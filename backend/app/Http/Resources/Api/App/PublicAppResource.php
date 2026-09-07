<?php

namespace App\Http\Resources\Api\App;

use App\Models\AppPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public landing config for a published app — nothing sensitive.
 *
 * @mixin AppPublication
 */
class PublicAppResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->project->name,
            'welcome_message' => $this->welcome_message,
            'suggested_questions' => $this->suggested_questions ?? [],
        ];
    }
}
