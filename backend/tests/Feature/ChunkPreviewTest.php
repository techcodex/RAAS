<?php

use App\Models\Chunk;
use App\Models\Document;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

it('lists a document chunks in order', function () {
    $user = createOwner();
    $document = Document::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();
    Chunk::factory()->for($document)->count(3)
        ->sequence(fn ($s) => ['chunk_index' => 2 - $s->index, 'content' => "chunk {$s->index}"])
        ->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/documents/{$document->id}/chunks")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.chunk_index', 0)
        ->assertJsonPath('data.2.chunk_index', 2);
});

it('does not expose another organization chunks', function () {
    $foreign = Document::factory()->forProject(Project::factory()->create())->create();
    Chunk::factory()->for($foreign)->create();

    Sanctum::actingAs(createOwner());

    $this->getJson("/api/v1/documents/{$foreign->id}/chunks")->assertNotFound();
});
