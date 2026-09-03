<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\RagClient;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    /**
     * @param  array<string, mixed>|null  $strategyConfig
     */
    public function __construct(
        public Document $document,
        public ?string $strategy = null,
        public ?array $strategyConfig = null,
    ) {}

    public function handle(RagClient $rag): void
    {
        // Jobs run outside the request lifecycle, so bind the tenant explicitly
        // before any tenant-scoped query runs.
        app(TenantContext::class)->set($this->document->organization);

        $document = $this->document->fresh(['project']);
        if ($document === null) {
            return;
        }

        $document->update(['status' => DocumentStatus::Chunking, 'error_message' => null]);

        $project = $document->project;
        $strategy = $this->strategy ?? $project->chunking_strategy ?? 'auto';

        // Normalise an empty config to null so it serialises as JSON null, not `[]`.
        $config = $this->strategyConfig ?: $project->chunking_config;
        $config = empty($config) ? null : $config;

        try {
            $result = $rag->process([
                'document_id' => $document->id,
                'organization_id' => $document->organization_id,
                'project_id' => $project->id,
                'collection' => $project->vectorCollection(),
                'filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'download_url' => Storage::disk($document->disk)->temporaryUrl(
                    $document->path, now()->addMinutes(20)
                ),
                'strategy' => $strategy,
                'strategy_config' => $config,
                'embedder' => $project->embedderConfig(),
                'replace' => true,
            ]);
        } catch (RequestException $e) {
            // 4xx means the request/document itself is the problem — do not retry.
            if ($e->response->clientError()) {
                $document->markFailed($this->reason($e));

                return;
            }
            throw $e;
        }

        $this->storeResult($document, $result, $config);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $config
     */
    private function storeResult(Document $document, array $result, ?array $config): void
    {
        DB::transaction(function () use ($document, $result, $config) {
            $document->chunks()->delete();

            $document->chunks()->createMany(collect($result['chunks'])->map(fn (array $chunk) => [
                'organization_id' => $document->organization_id,
                'chunk_index' => $chunk['index'],
                'content' => $chunk['text'],
                'token_count' => $chunk['token_count'] ?? 0,
                'metadata' => $chunk['metadata'] ?? [],
            ])->all());

            $now = now();
            $document->update([
                'status' => DocumentStatus::Ready,
                'chunk_count' => $result['chunk_count'],
                'chunking_strategy' => $result['strategy'],
                'chunking_config' => $config,
                'processed_at' => $now,
                'error_message' => null,
            ]);

            $project = $document->project;
            if ($project->embedding_model_id === null) {
                // System-managed binding, not user input — bypass $fillable.
                $project->forceFill([
                    'embedding_model_id' => $result['model_id'],
                    'embedding_dimension' => $result['dimension'],
                ])->save();
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->document->fresh()?->markFailed(
            $exception?->getMessage() ?? 'Processing failed.'
        );
    }

    /**
     * Human-readable reason from a rag-service error response. FastAPI returns
     * `{"detail": "..."}` for raised errors and `{"detail": [...]}` for request
     * validation failures.
     */
    private function reason(RequestException $e): string
    {
        $detail = $e->response->json('detail');

        return match (true) {
            is_string($detail) => $detail,
            is_array($detail) => json_encode($detail),
            default => $e->getMessage(),
        };
    }
}
