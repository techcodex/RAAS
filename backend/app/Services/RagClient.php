<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Python rag-service. Internal calls are authenticated with an
 * HMAC signature over `"{timestamp}." + rawBody` using the shared RAG secret.
 */
class RagClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $secret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.rag.url'), '/'),
            (string) config('services.rag.secret'),
        );
    }

    /**
     * Chunking strategies and embedders the service offers, with their JSON-schema config.
     *
     * @return array<string, mixed>
     */
    public function strategies(): array
    {
        return Http::acceptJson()
            ->timeout(15)
            ->get("{$this->baseUrl}/strategies")
            ->throw()
            ->json();
    }

    /**
     * Run the parse → chunk → embed → upsert pipeline for one document.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function process(array $payload): array
    {
        return $this->post('/process', $payload, timeout: 600)->json();
    }

    /**
     * @param  array{provider: string, model: string|null}|null  $embedder
     * @return array<string, mixed>
     */
    public function embedQuery(string $text, ?array $embedder = null): array
    {
        return $this->post('/embed-query', ['text' => $text, 'embedder' => $embedder], timeout: 120)->json();
    }

    /**
     * Stream a project's vectors out of Qdrant as NDJSON (manifest line first).
     */
    public function export(string $collection): Response
    {
        return $this->post('/export', ['collection' => $collection], timeout: 300, stream: true);
    }

    public function dropCollection(string $collection): void
    {
        $this->post('/collections/drop', ['collection' => $collection], timeout: 60);
    }

    public function deleteDocument(string $collection, int $documentId): void
    {
        $this->post('/documents/purge', ['collection' => $collection, 'document_id' => $documentId], timeout: 60);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload, int $timeout, bool $stream = false): Response
    {
        $timestamp = (string) time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);

        $request = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-RAG-Timestamp' => $timestamp,
            'X-RAG-Signature' => $signature,
        ])->timeout($timeout);

        if ($stream) {
            $request->withOptions(['stream' => true]);
        }

        return $request->withBody($body, 'application/json')
            ->post("{$this->baseUrl}{$path}")
            ->throw();
    }
}
