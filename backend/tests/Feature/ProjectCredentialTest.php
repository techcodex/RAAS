<?php

use App\Models\Project;
use App\Models\ProjectCredential;
use Laravel\Sanctum\Sanctum;

it('reports no credential configured yet', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/credentials")->assertNoContent();
});

it('stores an api key without ever returning it', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'api_key' => 'sk-ant-abcdefghijklmnop',
        'model' => 'claude-sonnet-5',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'anthropic')
        ->assertJsonPath('data.model', 'claude-sonnet-5')
        ->assertJsonPath('data.configured', true)
        ->assertJsonMissingPath('data.api_key');

    expect($response->getContent())->not->toContain('sk-ant-abcdefghijklmnop');

    $stored = ProjectCredential::firstOrFail();
    expect($stored->api_key)->toBe('sk-ant-abcdefghijklmnop'); // decrypted transparently
    expect($stored->getRawOriginal('api_key'))->not->toContain('sk-ant-abcdefghijklmnop'); // encrypted at rest
});

it('replaces an existing key rather than creating a second row', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['api_key' => 'sk-ant-first-key-value'])->assertCreated();
    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['api_key' => 'sk-ant-second-key-value'])->assertOk();

    expect(ProjectCredential::count())->toBe(1)
        ->and(ProjectCredential::firstOrFail()->api_key)->toBe('sk-ant-second-key-value');
});

it('rejects a too-short api key', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['api_key' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('api_key');
});

it('rejects an unsupported model', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'api_key' => 'sk-ant-abcdefghijklmnop',
        'model' => 'gpt-5',
    ])->assertUnprocessable()->assertJsonValidationErrors('model');
});

it('deletes the credential', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    ProjectCredential::factory()->for($project)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/projects/{$project->id}/credentials")->assertNoContent();
    expect(ProjectCredential::count())->toBe(0);
});

it('does not expose or let another organization manage a project credential', function () {
    $foreign = Project::factory()->create();
    ProjectCredential::factory()->for($foreign)->create();
    Sanctum::actingAs(createOwner());

    $this->getJson("/api/v1/projects/{$foreign->id}/credentials")->assertNotFound();
    $this->postJson("/api/v1/projects/{$foreign->id}/credentials", ['api_key' => 'sk-ant-abcdefghijklmnop'])->assertNotFound();
    $this->deleteJson("/api/v1/projects/{$foreign->id}/credentials")->assertNotFound();
});
