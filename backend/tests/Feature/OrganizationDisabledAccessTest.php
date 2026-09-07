<?php

use App\Enums\OrganizationStatus;
use Laravel\Sanctum\Sanctum;

it('blocks tenant routes for a user whose organization is disabled', function () {
    $owner = createOwner();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/projects')->assertOk();

    $owner->currentOrganization->forceFill(['status' => OrganizationStatus::Disabled])->save();

    $this->getJson('/api/v1/projects')
        ->assertForbidden()
        ->assertJsonPath('message', 'This organization has been disabled.');
});

it('still allows an active organization through', function () {
    $owner = createOwner();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/projects')->assertOk();
});

it('does not block /auth/me or /auth/logout for a disabled organization', function () {
    $owner = createOwner();
    $owner->currentOrganization->forceFill(['status' => OrganizationStatus::Disabled])->save();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/auth/me')->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertOk();
});
