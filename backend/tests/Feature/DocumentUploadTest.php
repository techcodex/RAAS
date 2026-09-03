<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
});

it('uploads a document and stores it at status uploaded', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->create('policy.pdf', 120, 'application/pdf');

    $response = $this->postJson("/api/v1/projects/{$project->id}/documents", [
        'files' => [$file],
    ]);

    $response->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_filename', 'policy.pdf')
        ->assertJsonPath('data.0.status', 'uploaded')
        ->assertJsonPath('data.0.uploaded_by.id', $user->id);

    $document = Document::firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->organization_id)->toBe($user->current_organization_id)
        ->and($document->project_id)->toBe($project->id);

    Storage::disk('s3')->assertExists($document->path);
});

it('uploads multiple documents in one request', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/documents", [
        'files' => [
            UploadedFile::fake()->create('a.txt', 10, 'text/plain'),
            UploadedFile::fake()->create('b.md', 10, 'text/markdown'),
        ],
    ])->assertCreated()->assertJsonCount(2, 'data');

    expect($project->documents()->count())->toBe(2);
});

it('rejects a disallowed file type', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/documents", [
        'files' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
    ])->assertUnprocessable()->assertJsonValidationErrors('files.0');

    expect(Document::count())->toBe(0);
});

it('rejects a file over the size limit', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $tooBig = config('raas.documents.max_size_kb') + 1;

    $this->postJson("/api/v1/projects/{$project->id}/documents", [
        'files' => [UploadedFile::fake()->create('big.pdf', $tooBig, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('files.0');
});

it('requires at least one file', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/documents", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('files');
});

it('enforces the per-project document quota', function () {
    config(['raas.documents.per_project_quota' => 1]);

    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Document::factory()->forProject($project)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$project->id}/documents", [
        'files' => [UploadedFile::fake()->create('extra.pdf', 10, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('files');
});

it('lists documents for a project newest first', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    $old = Document::factory()->forProject($project)->create(['created_at' => now()->subDay()]);
    $new = Document::factory()->forProject($project)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/projects/{$project->id}/documents")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

it('deletes a document and removes the stored file', function () {
    $user = createOwner();
    $project = Project::factory()->for($user->currentOrganization)->create();
    Sanctum::actingAs($user);

    $file = UploadedFile::fake()->create('policy.pdf', 20, 'application/pdf');
    $this->postJson("/api/v1/projects/{$project->id}/documents", ['files' => [$file]]);
    $document = Document::firstOrFail();

    Storage::disk('s3')->assertExists($document->path);

    $this->deleteJson("/api/v1/documents/{$document->id}")->assertNoContent();

    $this->assertModelMissing($document);
    Storage::disk('s3')->assertMissing($document->path);
});

it('does not let a user upload to another organization project', function () {
    $user = createOwner();
    $foreign = Project::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/projects/{$foreign->id}/documents", [
        'files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
    ])->assertNotFound();

    expect(Document::count())->toBe(0);
});

it('does not let a user read or delete another organization document', function () {
    $user = createOwner();
    $foreignProject = Project::factory()->create();
    $foreignDocument = Document::factory()->forProject($foreignProject)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/documents/{$foreignDocument->id}")->assertNotFound();
    $this->deleteJson("/api/v1/documents/{$foreignDocument->id}")->assertNotFound();

    $this->assertModelExists($foreignDocument);
});
