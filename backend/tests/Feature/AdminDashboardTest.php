<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

it('rejects non-admin and unauthenticated requests', function () {
    $this->getJson('/api/v1/admin/stats')->assertUnauthorized();

    Sanctum::actingAs(createOwner());
    $this->getJson('/api/v1/admin/stats')->assertForbidden();
});

it('returns platform-wide totals across every tenant', function () {
    $alpha = createOwner();
    $beta = createOwner();
    Organization::factory()->disabled()->create();

    $p1 = Project::factory()->for($alpha->currentOrganization)->create();
    $p2 = Project::factory()->for($beta->currentOrganization)->create();
    Document::factory()->forProject($p1)->status(DocumentStatus::Ready)->count(2)->create();
    Document::factory()->forProject($p2)->status(DocumentStatus::Failed)->create();
    Document::factory()->forProject($p2)->status(DocumentStatus::Uploaded)->create();

    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/stats')
        ->assertOk()
        ->assertJsonPath('organizations.total', 3)
        ->assertJsonPath('organizations.active', 2)
        ->assertJsonPath('organizations.disabled', 1)
        ->assertJsonPath('projects.total', 2)
        ->assertJsonPath('documents.total', 4)
        ->assertJsonPath('documents.by_status.ready', 2)
        ->assertJsonPath('documents.by_status.failed', 1)
        ->assertJsonPath('documents.by_status.uploaded', 1)
        ->assertJsonPath('documents.by_status.chunking', 0);
});
