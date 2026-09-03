<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Chunk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Chunk
 */
class ChunkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'chunk_index' => $this->chunk_index,
            'content' => $this->content,
            'token_count' => $this->token_count,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
