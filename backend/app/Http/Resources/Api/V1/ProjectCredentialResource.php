<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProjectCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never exposes the API key — not even a masked fragment.
 *
 * @mixin ProjectCredential
 */
class ProjectCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'configured' => true,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
