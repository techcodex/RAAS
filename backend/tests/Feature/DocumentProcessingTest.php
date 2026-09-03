<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
});

it('queues a document for processing', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $document = Document::factory()->forProject($project)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/documents/{$document->id}/process", ['strategy' => 'recursive'])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued');

    expect($document->refresh()->status)->toBe(DocumentStatus::Queued);

    Queue::assertPushed(ProcessDocument::class, function (ProcessDocument $job) use ($document) {
        return $job->document->is($document) && $job->strategy === 'recursive';
    });
});

it('defaults the strategy to the project setting', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $document = Document::factory()->forProject($project)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/documents/{$document->id}/process")->assertAccepted();

    Queue::assertPushed(ProcessDocument::class, fn (ProcessDocument $job) => $job->strategy === null);
});

it('rejects an unknown strategy', function () {
    $user = createOwner();
    $document = Document::factory()->forProject(
        Project::factory()->for($user->currentOrganization)->create()
    )->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/documents/{$document->id}/process", ['strategy' => 'telepathy'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('strategy');

    Queue::assertNothingPushed();
});

it('will not process another organization document', function () {
    $user = createOwner();
    $foreign = Document::factory()->forProject(Project::factory()->create())->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/documents/{$foreign->id}/process")->assertNotFound();

    Queue::assertNothingPushed();
});
