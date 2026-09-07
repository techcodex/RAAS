<?php

use Laravel\Sanctum\Sanctum;

it('rejects non-admin and unauthenticated requests', function () {
    $this->getJson('/api/v1/admin/settings')->assertUnauthorized();

    Sanctum::actingAs(createOwner());
    $this->getJson('/api/v1/admin/settings')->assertForbidden();
});

it('returns the read-only platform configuration', function () {
    config([
        'raas.documents.max_size_kb' => 51200,
        'raas.documents.allowed_extensions' => ['pdf', 'txt'],
        'raas.organizations.default_document_limit' => 1000,
    ]);

    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonPath('documents.max_size_kb', 51200)
        ->assertJsonPath('documents.allowed_extensions', ['pdf', 'txt'])
        ->assertJsonPath('organizations.default_document_limit', 1000);
});
