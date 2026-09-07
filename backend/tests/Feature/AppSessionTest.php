<?php

use App\Enums\OrganizationStatus;

it('returns the public landing config for an active app', function () {
    $publication = publishedApp(null, [
        'welcome_message' => 'Welcome to the handbook.',
        'suggested_questions' => ['One?', 'Two?'],
    ]);

    $this->getJson("/api/app/{$publication->slug}")
        ->assertOk()
        ->assertJsonPath('data.welcome_message', 'Welcome to the handbook.')
        ->assertJsonPath('data.suggested_questions', ['One?', 'Two?'])
        ->assertJsonMissingPath('data.access_code');
});

it('404s an unknown, inactive, or disabled-org app', function () {
    $this->getJson('/api/app/nope')->assertNotFound();

    $inactive = publishedApp(null, ['is_active' => false]);
    $this->getJson("/api/app/{$inactive->slug}")->assertNotFound();

    $publication = publishedApp();
    $publication->project->organization->forceFill(['status' => OrganizationStatus::Disabled])->save();
    $this->getJson("/api/app/{$publication->slug}")->assertNotFound();
});

it('signs a registered employee in with their email and access code', function () {
    $publication = publishedApp();
    $employee = addEmployee($publication, ['name' => 'Jane Doe', 'email' => 'jane@acme.test']);

    $this->postJson("/api/app/{$publication->slug}/session", [
        'email' => 'jane@acme.test',
        'access_code' => 'LETMEIN',
    ])->assertOk()
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.email', 'jane@acme.test')
        ->assertJsonStructure(['token']);

    expect($employee->fresh()->last_seen_at)->not->toBeNull();
});

it('rejects an email that is not a registered employee', function () {
    $publication = publishedApp();

    $this->postJson("/api/app/{$publication->slug}/session", [
        'email' => 'stranger@acme.test',
        'access_code' => 'LETMEIN',
    ])->assertUnprocessable()->assertJsonValidationErrors('access_code');
});

it('rejects a wrong access code', function () {
    $publication = publishedApp();
    addEmployee($publication, ['email' => 'jane@acme.test']);

    $this->postJson("/api/app/{$publication->slug}/session", [
        'email' => 'jane@acme.test',
        'access_code' => 'WRONG',
    ])->assertUnprocessable()->assertJsonValidationErrors('access_code');
});

it('rejects a deactivated employee at sign-in', function () {
    $publication = publishedApp();
    addEmployee($publication, ['email' => 'jane@acme.test'])->update(['is_active' => false]);

    $this->postJson("/api/app/{$publication->slug}/session", [
        'email' => 'jane@acme.test',
        'access_code' => 'LETMEIN',
    ])->assertUnprocessable()->assertJsonValidationErrors('access_code');
});
