<?php

use App\Models\Project;
use App\Services\RagClient;
use Illuminate\Http\Client\Response;
use Laravel\Sanctum\Sanctum;

it('refuses to export a project with no embeddings', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/export")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This project has no embeddings yet. Process at least one document first.');
});

it('streams the ndjson bundle from the rag-service', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create([
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
    ]);

    $ndjson = implode("\n", [
        json_encode(['type' => 'raas.embeddings.manifest', 'dimension' => 384]),
        json_encode(['id' => 'abc', 'vector' => [0.1, 0.2], 'payload' => ['text' => 'hi']]),
    ]);
    $response = new Response(new GuzzleHttp\Psr7\Response(200, [], $ndjson));

    $this->mock(RagClient::class)
        ->shouldReceive('export')->once()
        ->with($project->vectorCollection())
        ->andReturn($response);

    Sanctum::actingAs($user);

    $res = $this->get("/api/v1/projects/{$project->id}/export");
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/x-ndjson')
        ->and($res->streamedContent())->toContain('raas.embeddings.manifest');
});

it('will not export another organization project', function () {
    $foreign = Project::factory()->create(['embedding_model_id' => 'm', 'embedding_dimension' => 3]);
    Sanctum::actingAs(createOwner());

    $this->getJson("/api/v1/projects/{$foreign->id}/export")->assertNotFound();
});
