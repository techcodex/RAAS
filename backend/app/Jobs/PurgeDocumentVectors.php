<?php

namespace App\Jobs;

use App\Services\RagClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

/**
 * Removes a deleted document's points from its project's Qdrant collection.
 * The chunks table rows are already gone via the FK cascade.
 */
class PurgeDocumentVectors implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $documentId,
        public string $collection,
    ) {}

    public function handle(RagClient $rag): void
    {
        try {
            $rag->deleteDocument($this->collection, $this->documentId);
        } catch (RequestException $e) {
            // A missing collection just means nothing was ever embedded.
            if ($e->response->notFound()) {
                return;
            }
            throw $e;
        }
    }
}
