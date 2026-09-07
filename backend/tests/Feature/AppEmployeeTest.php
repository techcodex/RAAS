<?php

use App\Models\AppConversation;
use App\Models\AppUser;
use App\Models\Project;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('lists a publication\'s employees', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    $publication = publishedApp($project);
    addEmployee($publication, ['name' => 'Bea', 'email' => 'bea@acme.test']);
    addEmployee($publication, ['name' => 'Al', 'email' => 'al@acme.test']);
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/projects/{$project->id}/publication/employees")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Al')
        ->assertJsonMissingPath('data.0.access_code');
});

it('adds an employee and returns a generated access code once', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    publishedApp($project);
    Sanctum::actingAs($owner);

    $response = $this->postJson("/api/v1/projects/{$project->id}/publication/employees", [
        'name' => 'Jane Doe',
        'email' => 'Jane@Acme.test',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@acme.test')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data', 'access_code']);

    $code = $response->json('access_code');
    $employee = AppUser::where('email', 'jane@acme.test')->firstOrFail();
    expect($code)->toHaveLength(8)
        ->and(Hash::check($code, $employee->access_code))->toBeTrue();
});

it('accepts an owner-supplied access code', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    publishedApp($project);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/projects/{$project->id}/publication/employees", [
        'name' => 'Jane', 'email' => 'jane@acme.test', 'access_code' => 'jane-secret',
    ])->assertCreated()->assertJsonPath('access_code', 'jane-secret');
});

it('deactivates and reactivates an employee', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    $employee = addEmployee(publishedApp($project), ['email' => 'jane@acme.test']);
    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/projects/{$project->id}/publication/employees/{$employee->id}", [
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    expect($employee->fresh()->is_active)->toBeFalse();

    $this->patchJson("/api/v1/projects/{$project->id}/publication/employees/{$employee->id}", [
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.is_active', true);
});

it('removes an employee and their conversations', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    $employee = addEmployee(publishedApp($project), ['email' => 'jane@acme.test']);
    AppConversation::factory()->forEmployee($employee)->create();
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/projects/{$project->id}/publication/employees/{$employee->id}")
        ->assertNoContent();

    expect(AppUser::find($employee->id))->toBeNull()
        ->and(AppConversation::where('app_user_id', $employee->id)->count())->toBe(0);
});

it('resets an employee access code', function () {
    $owner = createOwner();
    $project = publishableProject($owner->currentOrganization);
    $employee = addEmployee(publishedApp($project), ['email' => 'jane@acme.test']);
    Sanctum::actingAs($owner);

    $response = $this->postJson(
        "/api/v1/projects/{$project->id}/publication/employees/{$employee->id}/reset-code",
    )->assertOk()->assertJsonStructure(['access_code']);

    expect(Hash::check($response->json('access_code'), $employee->fresh()->access_code))->toBeTrue()
        ->and(Hash::check('LETMEIN', $employee->fresh()->access_code))->toBeFalse();
});

it('does not let an owner manage another organization\'s employees', function () {
    $foreign = Project::factory()->create();
    Sanctum::actingAs(createOwner());

    $this->getJson("/api/v1/projects/{$foreign->id}/publication/employees")->assertNotFound();
    $this->postJson("/api/v1/projects/{$foreign->id}/publication/employees", [
        'name' => 'x', 'email' => 'x@y.test',
    ])->assertNotFound();
});
