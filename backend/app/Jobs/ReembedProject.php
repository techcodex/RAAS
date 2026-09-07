<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\ReembedStatus;
use App\Models\Chunk;
use App\Models\Project;
use App\Services\RagClient;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds a project's Qdrant collection under a newly-selected embedding model,
 * re-embedding every ready document from its stored chunks (no re-parsing).
 * Dispatched by ProjectController::update when the embedder model changes.
 */
class ReembedProject implements ShouldQueue
{
    use Queueable;

    // A destructive rebuild — don't auto-retry; failures are recorded on the row.
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public Project $project) {}

    public function handle(RagClient $rag): void
    {
        // Jobs run outside the request lifecycle — bind the tenant before any
        // tenant-scoped query runs.
        app(TenantContext::class)->set($this->project->organization);

        $project = $this->project->fresh();
        if ($project === null || ! $project->isReembedding()) {
            return;
        }

        $project->forceFill(['reembed_status' => ReembedStatus::Running])->save();

        $collection = $project->vectorCollection();

        try {
            // Start from an empty collection so a new vector dimension can take effect.
            $rag->dropCollection($collection);

            $modelId = null;
            $dimension = null;

            $documents = $project->documents()
                ->where('status', DocumentStatus::Ready)
                ->with('chunks')
                ->get();

            foreach ($documents as $document) {
                if ($document->chunks->isEmpty()) {
                    continue;
                }

                $result = $rag->embedDocument([
                    'collection' => $collection,
                    'organization_id' => $project->organization_id,
                    'project_id' => $project->id,
                    'document_id' => $document->id,
                    'embedder' => $project->embedderConfig(),
                    'chunks' => $document->chunks->sortBy('chunk_index')->map(fn (Chunk $chunk) => [
                        'index' => $chunk->chunk_index,
                        'text' => $chunk->content,
                        'metadata' => $chunk->metadata ?? [],
                    ])->values()->all(),
                    'replace' => true,
                ]);

                $modelId = $result['model_id'];
                $dimension = $result['dimension'];
            }

            $project->forceFill([
                'embedding_model_id' => $modelId ?? $project->embedding_model_id,
                'embedding_dimension' => $dimension ?? $project->embedding_dimension,
                'reembed_status' => null,
                'reembed_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error("Re-embed failed for project {$project->id}: {$e->getMessage()}");

            $project->forceFill([
                'reembed_status' => ReembedStatus::Failed,
                'reembed_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();
        }
    }
}
