<?php

use App\Enums\ReembedStatus;
use App\Jobs\ReembedProject;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

it('lists only the current organization projects', function () {
    $user = createOwner();
    $owned = Project::factory()->for($user->currentOrganization)->count(2)->create();
    Project::factory()->count(3)->create(); // other organizations

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $owned->sortByDesc('id')->first()->id);
});

it('creates a project in the current organization', function () {
    $user = createOwner();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/projects', ['name' => 'Handbook', 'description' => 'HR docs'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Handbook')
        ->assertJsonPath('data.documents_count', 0);

    $this->assertDatabaseHas('projects', [
        'name' => 'Handbook',
        'organization_id' => $user->current_organization_id,
    ]);
});

it('requires a name to create a project', function () {
    Sanctum::actingAs(createOwner());

    $this->postJson('/api/v1/projects', ['description' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('shows a project from the current organization', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id);
});

it('updates a project', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($project->refresh()->name)->toBe('Renamed');
});

it('queues a re-embed when the embedding model changes on a project with embeddings', function () {
    Queue::fake();
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create([
        'embedder_model' => 'BAAI/bge-small-en-v1.5',
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/projects/{$project->id}", ['embedder_model' => 'BAAI/bge-base-en-v1.5'])
        ->assertOk()
        ->assertJsonPath('data.embedder.model', 'BAAI/bge-base-en-v1.5')
        ->assertJsonPath('data.embedder.reembed_status', 'queued');

    Queue::assertPushed(ReembedProject::class, fn (ReembedProject $job) => $job->project->is($project));
    expect($project->refresh()->reembed_status)->toBe(ReembedStatus::Queued);
});

it('does not queue a re-embed when the project has no embeddings yet', function () {
    Queue::fake();
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/projects/{$project->id}", ['embedder_model' => 'BAAI/bge-base-en-v1.5'])
        ->assertOk()
        ->assertJsonPath('data.embedder.reembed_status', null);

    Queue::assertNothingPushed();
    expect($project->refresh()->embedder_model)->toBe('BAAI/bge-base-en-v1.5');
});

it('does not queue a re-embed when the model is unchanged', function () {
    Queue::fake();
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create([
        'embedder_model' => 'BAAI/bge-small-en-v1.5',
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/projects/{$project->id}", ['embedder_model' => 'BAAI/bge-small-en-v1.5'])
        ->assertOk();

    Queue::assertNothingPushed();
});

it('rejects settings changes while a re-embed is running', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create([
        'reembed_status' => ReembedStatus::Running,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Renamed'])
        ->assertStatus(409);

    expect($project->refresh()->name)->not->toBe('Renamed');
});

it('deletes a project', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('hides projects belonging to another organization', function () {
    $user = createOwner();
    $foreign = Project::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/v1/projects/{$foreign->id}", ['name' => 'x'])->assertNotFound();
    $this->deleteJson("/api/v1/projects/{$foreign->id}")->assertNotFound();

    $this->assertDatabaseHas('projects', ['id' => $foreign->id]);
});
