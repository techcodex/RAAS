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

it('stores an anthropic key without ever returning it', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'provider' => 'anthropic',
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

it('stores a gemini key and defaults its model', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'provider' => 'gemini',
        'api_key' => 'AIzaSyFAKE1234567890',
    ])->assertCreated()
        ->assertJsonPath('data.provider', 'gemini')
        ->assertJsonPath('data.model', 'gemini-3.8-flash');
});

it('replaces an existing key rather than creating a second row', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['provider' => 'anthropic', 'api_key' => 'sk-ant-first-key-value'])->assertCreated();
    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['provider' => 'gemini', 'api_key' => 'AIzaSySECONDKEYVALUE'])->assertOk();

    $stored = ProjectCredential::firstOrFail();
    expect(ProjectCredential::count())->toBe(1)
        ->and($stored->provider)->toBe('gemini')
        ->and($stored->api_key)->toBe('AIzaSySECONDKEYVALUE');
});

it('rejects a too-short api key', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['provider' => 'anthropic', 'api_key' => 'short'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('api_key');
});

it('requires a provider', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", ['api_key' => 'sk-ant-abcdefghijklmnop'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');
});

it('rejects an unsupported provider', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'provider' => 'openai', 'api_key' => 'sk-abcdefghijklmnop',
    ])->assertUnprocessable()->assertJsonValidationErrors('provider');
});

it('rejects a model that does not belong to the chosen provider', function () {
    Sanctum::actingAs($user = createOwner());
    $project = Project::factory()->for($user->currentOrganization)->create();

    $this->postJson("/api/v1/projects/{$project->id}/credentials", [
        'provider' => 'anthropic', 'api_key' => 'sk-ant-abcdefghijklmnop', 'model' => 'gemini-3.8-flash',
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
    $this->postJson("/api/v1/projects/{$foreign->id}/credentials", ['provider' => 'anthropic', 'api_key' => 'sk-ant-abcdefghijklmnop'])->assertNotFound();
    $this->deleteJson("/api/v1/projects/{$foreign->id}/credentials")->assertNotFound();
});
