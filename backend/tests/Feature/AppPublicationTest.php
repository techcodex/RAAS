<?php

use App\Models\AppPublication;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

it('returns null when a project has no publication', function () {
    $owner = createOwner();
    $project = Project::factory()->for($owner->currentOrganization)->create();
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/projects/{$project->id}/publication")
        ->assertOk()
        ->assertExactJson(['data' => null]);
});

it('creates a draft publication with a generated slug', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/projects/{$project->id}/publication", [
        'welcome_message' => 'Ask the handbook anything.',
        'suggested_questions' => ['How much leave?', 'Expense policy?'],
    ])->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.welcome_message', 'Ask the handbook anything.');

    $publication = AppPublication::withoutGlobalScopes()->firstOrFail();
    expect($publication->slug)->not->toBeEmpty()
        ->and($publication->project_id)->toBe($project->id);
});

it('refuses to activate without embeddings or an llm credential', function () {
    $owner = createOwner();
    $bare = Project::factory()->for($owner->currentOrganization)->create();
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/projects/{$bare->id}/publication", ['is_active' => true])
        ->assertStatus(422);

    expect(AppPublication::withoutGlobalScopes()->count())->toBe(0);
});

it('activates a publication for a ready project', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/projects/{$project->id}/publication", []);
    $this->putJson("/api/v1/projects/{$project->id}/publication", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('does not let an owner touch another organization\'s project publication', function () {
    $owner = createOwner();
    $foreign = Project::factory()->create();
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/projects/{$foreign->id}/publication")->assertNotFound();
    $this->putJson("/api/v1/projects/{$foreign->id}/publication", [])->assertNotFound();
});

it('rejects a publication write from a non-owner', function () {
    $project = publishableProject();

    $this->putJson("/api/v1/projects/{$project->id}/publication", [])->assertUnauthorized();
});
