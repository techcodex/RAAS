<?php

use App\Actions\RegisterOrganizationOwner;
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
