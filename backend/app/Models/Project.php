<?php

namespace App\Models;

use App\Support\BelongsToOrganization;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'chunking_strategy', 'chunking_config', 'embedder_provider', 'embedder_model'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'chunking_config' => 'array',
            'embedding_dimension' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Name of this project's dedicated Qdrant collection. A project owns one
     * collection so it can be exported and re-embedded independently.
     */
    public function vectorCollection(): string
    {
        return "project_{$this->id}";
    }

    /**
     * The embedder selection sent to the rag-service, or null to use its default.
     *
     * @return array{provider: string, model: string|null}
     */
    public function embedderConfig(): array
    {
        return [
            'provider' => $this->embedder_provider ?? 'local',
            'model' => $this->embedder_model,
        ];
    }
}
