<?php

use App\Enums\DocumentStatus;
use App\Enums\ReembedStatus;
use App\Jobs\ReembedProject;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Project;
use App\Services\RagClient;

function reembedProject(array $projectAttributes = []): Project
{
    $owner = createOwner();

    return Project::factory()->for($owner->currentOrganization)->create(array_merge([
        'embedder_model' => 'BAAI/bge-base-en-v1.5',
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
        'reembed_status' => ReembedStatus::Queued,
    ], $projectAttributes));
}

it('rebuilds the collection and re-embeds every ready document from its stored chunks', function () {
    $project = reembedProject();

    $ready = Document::factory()->forProject($project)->status(DocumentStatus::Ready)->create();
    Chunk::factory()->for($ready)->count(3)->sequence(fn ($s) => ['chunk_index' => $s->index])->create();

    // A still-processing document is skipped.
    Document::factory()->forProject($project)->status(DocumentStatus::Queued)->create();

    $rag = $this->mock(RagClient::class);
    $rag->shouldReceive('dropCollection')->once()->with($project->vectorCollection());
    $rag->shouldReceive('embedDocument')->once()
        ->withArgs(function (array $payload) use ($project, $ready) {
            return $payload['collection'] === $project->vectorCollection()
                && $payload['document_id'] === $ready->id
                && count($payload['chunks']) === 3
                && $payload['chunks'][0]['index'] === 0
                && $payload['embedder']['model'] === 'BAAI/bge-base-en-v1.5';
        })
        ->andReturn(['model_id' => 'BAAI/bge-base-en-v1.5', 'dimension' => 768]);

    (new ReembedProject($project))->handle(app(RagClient::class));

    $project->refresh();
    expect($project->reembed_status)->toBeNull()
        ->and($project->reembed_error)->toBeNull()
        ->and($project->embedding_model_id)->toBe('BAAI/bge-base-en-v1.5')
        ->and($project->embedding_dimension)->toBe(768);
});

it('records a failure on the project row when the rag-service rejects the model', function () {
    $project = reembedProject();
    Chunk::factory()
        ->for(Document::factory()->forProject($project)->status(DocumentStatus::Ready)->create())
        ->create();

    $rag = $this->mock(RagClient::class);
    $rag->shouldReceive('dropCollection')->once();
    $rag->shouldReceive('embedDocument')->andThrow(new RuntimeException('Unsupported local model.'));

    (new ReembedProject($project))->handle(app(RagClient::class));

    $project->refresh();
    expect($project->reembed_status)->toBe(ReembedStatus::Failed)
        ->and($project->reembed_error)->toBe('Unsupported local model.')
        // The old binding is left intact so a retry has a known starting point.
        ->and($project->embedding_model_id)->toBe('BAAI/bge-small-en-v1.5');
});

it('does nothing when the project is no longer marked for re-embed', function () {
    $project = reembedProject(['reembed_status' => null]);

    $this->mock(RagClient::class)->shouldNotReceive('dropCollection');

    (new ReembedProject($project))->handle(app(RagClient::class));

    expect($project->refresh()->reembed_status)->toBeNull();
});
