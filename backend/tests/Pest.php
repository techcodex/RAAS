<?php

use App\Actions\RegisterOrganizationOwner;
use App\Models\AppPublication;
use App\Models\AppUser;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Create a registered user who owns a fresh organization (their current tenant).
 *
 * @param  array<string, mixed>  $attributes
 */
function createOwner(array $attributes = []): User
{
    return app(RegisterOrganizationOwner::class)->handle(array_merge([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
    ], $attributes));
}

/**
 * Create a platform administrator — a platform-only account with no organization.
 *
 * @param  array<string, mixed>  $attributes
 */
function createAdmin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

/**
 * A project that is ready to publish: bound embeddings + an LLM credential.
 */
function publishableProject(?Organization $organization = null): Project
{
    $organization ??= createOwner()->currentOrganization;

    $project = Project::factory()->for($organization)->create([
        'embedding_model_id' => 'BAAI/bge-small-en-v1.5',
        'embedding_dimension' => 384,
    ]);
    ProjectCredential::factory()->for($project)->create();

    return $project;
}

/**
 * A published employee app for a ready project.
 *
 * @param  array<string, mixed>  $attributes
 */
function publishedApp(?Project $project = null, array $attributes = []): AppPublication
{
    $project ??= publishableProject();

    return AppPublication::factory()->forProject($project)->create($attributes);
}

/**
 * Add an employee to a publication with access code "LETMEIN".
 *
 * @param  array<string, mixed>  $attributes
 */
function addEmployee(AppPublication $publication, array $attributes = []): AppUser
{
    return AppUser::factory()->forPublication($publication)->create(array_merge([
        'access_code' => 'LETMEIN',
    ], $attributes));
}
