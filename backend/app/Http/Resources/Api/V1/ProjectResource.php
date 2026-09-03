<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'documents_count' => $this->whenCounted('documents'),
            'chunking_strategy' => $this->chunking_strategy,
            'chunking_config' => $this->chunking_config,
            'embedder' => [
                'provider' => $this->embedder_provider,
                'model' => $this->embedder_model,
                'bound_model_id' => $this->embedding_model_id,
                'dimension' => $this->embedding_dimension,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
