<?php

use App\Models\Organization;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers a user with a personal organization and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'current_organization' => ['id', 'name', 'slug']]]);

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $organization = Organization::firstOrFail();

    expect($user->current_organization_id)->toBe($organization->id)
        ->and($organization->owner_id)->toBe($user->id)
        ->and($user->organizations()->pluck('role', 'organizations.id')->all())->toBe([$organization->id => 'owner']);
});

it('uses a provided organization name', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization_name' => 'Analytical Engines Ltd',
    ])->assertCreated();

    expect(Organization::firstOrFail()->name)->toBe('Analytical Engines Ltd');
});

it('rejects registration with a duplicate email', function () {
    createOwner(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects registration with an unconfirmed password', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'nope',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('logs in with valid credentials', function () {
    createOwner(['email' => 'ada@example.com', 'password' => 'secret-pw']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'secret-pw',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'current_organization']]);
});

it('rejects login with a wrong password', function () {
    createOwner(['email' => 'ada@example.com', 'password' => 'secret-pw']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('returns the authenticated user from /auth/me', function () {
    $user = createOwner();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.current_organization.id', $user->current_organization_id);
});

it('rejects unauthenticated access to protected routes', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/projects')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = createOwner();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
