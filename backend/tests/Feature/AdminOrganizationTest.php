<?php

use App\Enums\OrganizationStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a non-admin user', function () {
    Sanctum::actingAs(createOwner());

    $this->getJson('/api/v1/admin/organizations')->assertForbidden();
    $this->postJson('/api/v1/admin/organizations', ['name' => 'Nope'])->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/v1/admin/organizations')->assertUnauthorized();
});

it('lists every organization with counts, regardless of tenant', function () {
    createOwner(['organization_name' => 'Alpha Org']);
    $beta = createOwner(['organization_name' => 'Beta Org']);
    $project = Project::factory()->for($beta->currentOrganization)->create();
    Document::factory()->forProject($project)->count(2)->create();

    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/organizations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Alpha Org')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.documents_count', 0)
        ->assertJsonPath('data.1.name', 'Beta Org')
        ->assertJsonPath('data.1.documents_count', 2)
        ->assertJsonPath('data.1.projects_count', 1);
});

it('creates an organization with a provisioned owner account', function () {
    $admin = createAdmin();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/organizations', [
        'name' => 'Globex',
        'document_limit' => 250,
        'owner_name' => 'Grace Owner',
        'owner_email' => 'grace@globex.test',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Globex')
        ->assertJsonPath('data.document_limit', 250)
        ->assertJsonPath('data.owner.email', 'grace@globex.test')
        ->assertJsonStructure(['temporary_password']);

    $owner = User::where('email', 'grace@globex.test')->firstOrFail();
    $organization = Organization::find($response->json('data.id'));

    expect($owner->is_admin)->toBeFalse()
        ->and($owner->current_organization_id)->toBe($organization->id)
        ->and($organization->owner_id)->toBe($owner->id)
        ->and($organization->owner_id)->not->toBe($admin->id)
        ->and($owner->organizations()->pluck('role', 'organizations.id')->all())
        ->toBe([$organization->id => 'owner'])
        ->and($organization->slug)->toStartWith('globex-');
});

it('lets the provisioned owner sign in with the temporary password', function () {
    $adminToken = createAdmin()->createToken('admin')->plainTextToken;

    $password = $this->withToken($adminToken)->postJson('/api/v1/admin/organizations', [
        'name' => 'Initech',
        'owner_name' => 'Bill Lumbergh',
        'owner_email' => 'bill@initech.test',
    ])->assertCreated()->json('temporary_password');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'bill@initech.test',
        'password' => $password,
    ])->assertOk()
        ->assertJsonStructure(['token'])
        ->assertJsonPath('user.current_organization.name', 'Initech');
});

it('scopes the provisioned owner to their new organization', function () {
    $adminToken = createAdmin()->createToken('admin')->plainTextToken;

    $this->withToken($adminToken)->postJson('/api/v1/admin/organizations', [
        'name' => 'Initech',
        'owner_name' => 'Bill Lumbergh',
        'owner_email' => 'bill@initech.test',
    ])->assertCreated();

    Sanctum::actingAs(User::where('email', 'bill@initech.test')->firstOrFail());
    $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'data');
});

it('creates an organization with no explicit limit', function () {
    Sanctum::actingAs(createAdmin());

    $this->postJson('/api/v1/admin/organizations', [
        'name' => 'Umbrella',
        'owner_name' => 'Al Wesker',
        'owner_email' => 'wesker@umbrella.test',
    ])->assertCreated()
        ->assertJsonPath('data.document_limit', null)
        ->assertJsonPath('data.effective_document_limit', 1000);
});

it('rejects an owner email that already belongs to a user', function () {
    createOwner(['email' => 'taken@example.com']);
    Sanctum::actingAs(createAdmin());

    $this->postJson('/api/v1/admin/organizations', [
        'name' => 'Dupe',
        'owner_name' => 'Someone',
        'owner_email' => 'taken@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors('owner_email');
});

it('validates the create payload', function () {
    Sanctum::actingAs(createAdmin());

    $this->postJson('/api/v1/admin/organizations', ['name' => '', 'document_limit' => -5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'document_limit', 'owner_name', 'owner_email']);
});

it('updates an organization document limit', function () {
    $admin = createAdmin();
    $target = createOwner()->currentOrganization;
    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/organizations/{$target->id}", ['document_limit' => 5])
        ->assertOk()
        ->assertJsonPath('data.document_limit', 5);

    $this->patchJson("/api/v1/admin/organizations/{$target->id}", ['document_limit' => null])
        ->assertOk()
        ->assertJsonPath('data.document_limit', null);

    expect($target->fresh()->document_limit)->toBeNull();
});

it('disables an organization and revokes its members\' tokens', function () {
    $adminToken = createAdmin()->createToken('admin')->plainTextToken;
    $owner = createOwner();
    $owner->createToken('device');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/admin/organizations/{$owner->current_organization_id}/status", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    expect($owner->currentOrganization->fresh()->status)->toBe(OrganizationStatus::Disabled)
        ->and($owner->tokens()->count())->toBe(0);
});

it('leaves other organizations\' tokens alone when disabling one', function () {
    $adminToken = createAdmin()->createToken('admin')->plainTextToken;
    $target = createOwner();
    $target->createToken('device');
    $bystander = createOwner();
    $bystander->createToken('device');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/admin/organizations/{$target->current_organization_id}/status", ['status' => 'disabled'])
        ->assertOk();

    expect($target->tokens()->count())->toBe(0)
        ->and($bystander->tokens()->count())->toBe(1);
});

it('re-enables a disabled organization', function () {
    $admin = createAdmin();
    $target = Organization::factory()->disabled()->create();
    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/organizations/{$target->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($target->fresh()->status)->toBe(OrganizationStatus::Active);
});

it('does not touch the acting admin\'s token when disabling an organization', function () {
    $admin = createAdmin();
    $adminToken = $admin->createToken('admin')->plainTextToken;
    $target = createOwner()->currentOrganization;

    $this->withToken($adminToken)
        ->patchJson("/api/v1/admin/organizations/{$target->id}/status", ['status' => 'disabled'])
        ->assertOk();

    expect($admin->tokens()->count())->toBe(1);
    $this->withToken($adminToken)->getJson('/api/v1/admin/organizations')->assertOk();
});

it('validates the status value', function () {
    Sanctum::actingAs(createAdmin());
    $target = createOwner()->currentOrganization;

    $this->patchJson("/api/v1/admin/organizations/{$target->id}/status", ['status' => 'bogus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('rejects a non-admin from the status endpoint', function () {
    $target = createOwner()->currentOrganization;
    Sanctum::actingAs(createOwner());

    $this->patchJson("/api/v1/admin/organizations/{$target->id}/status", ['status' => 'disabled'])
        ->assertForbidden();
});

it('does not expose the is_admin flag as mass-assignable on register', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => true,
    ])->assertCreated()->assertJsonPath('user.is_admin', false);
});
