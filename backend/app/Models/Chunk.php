<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Database\Factories\ChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'organization_id', 'chunk_index', 'content', 'token_count', 'metadata'])]
class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'token_count' => 'integer',
            'chunk_index' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
