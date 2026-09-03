<?php

use App\Models\Project;
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
