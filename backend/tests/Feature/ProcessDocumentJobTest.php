<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Project;
use App\Services\RagClient;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

function fakeRagResult(array $overrides = []): array
{
    return array_merge([
        'status' => 'ready',
        'strategy' => 'recursive',
        'model_id' => 'BAAI/bge-small-en-v1.5',
        'dimension' => 384,
        'distance' => 'Cosine',
        'collection' => 'project_1',
        'chunk_count' => 2,
        'chunks' => [
            ['index' => 0, 'text' => 'First chunk.', 'token_count' => 3, 'metadata' => ['heading_path' => ['Intro']]],
            ['index' => 1, 'text' => 'Second chunk.', 'token_count' => 3, 'metadata' => []],
        ],
    ], $overrides);
}

it('stores chunks and marks the document ready', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $document = Document::factory()->forProject($project)->status(DocumentStatus::Queued)->create();

    $this->mock(RagClient::class)
        ->shouldReceive('process')->once()
        ->andReturn(fakeRagResult(['collection' => $project->vectorCollection()]));

    (new ProcessDocument($document))->handle(app(RagClient::class));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->chunk_count)->toBe(2)
        ->and($document->chunking_strategy)->toBe('recursive')
        ->and($document->chunks()->count())->toBe(2);

    $first = $document->chunks()->orderBy('chunk_index')->first();
    expect($first->content)->toBe('First chunk.')
        ->and($first->metadata['heading_path'])->toBe(['Intro']);

    expect($project->refresh()->embedding_model_id)->toBe('BAAI/bge-small-en-v1.5')
        ->and($project->embedding_dimension)->toBe(384);
});

it('replaces existing chunks when reprocessing', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $document = Document::factory()->forProject($project)->create();
    Chunk::factory()->for($document)->count(5)->sequence(fn ($s) => ['chunk_index' => $s->index])->create();

    $this->mock(RagClient::class)->shouldReceive('process')->andReturn(fakeRagResult());

    (new ProcessDocument($document))->handle(app(RagClient::class));

    expect($document->chunks()->count())->toBe(2);
});

it('marks the document failed on a 4xx from the rag-service without retrying', function () {
    $user = createOwner();
    $document = Document::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();

    $response = new Response(new GuzzleHttp\Psr7\Response(422, [], json_encode(['detail' => 'PDF has no text'])));
    $this->mock(RagClient::class)
        ->shouldReceive('process')
        ->andThrow(new RequestException($response));

    (new ProcessDocument($document))->handle(app(RagClient::class));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('PDF has no text');
});

it('marks the document failed via the failed() hook', function () {
    $document = Document::factory()->forProject(
        Project::factory()->for(createOwner()->currentOrganization)->create()
    )->create();

    (new ProcessDocument($document))->failed(new HttpClientException('connection refused'));

    expect($document->refresh()->status)->toBe(DocumentStatus::Failed);
});
