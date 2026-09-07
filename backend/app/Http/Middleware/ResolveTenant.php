<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's current organization into TenantContext so the
 * BelongsToOrganization global scope can isolate every tenant-owned query.
 * Runs after `auth:sanctum`.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Employee (AppUser) tokens resolve through the same Sanctum guard but
        // have no organization relations — keep them out of tenant routes.
        abort_unless($user instanceof User, 403, 'This route is not available to employee accounts.');

        $organization = $user->currentOrganization
            ?? $user->organizations()->first();

        abort_if($organization === null, 403, 'No organization is associated with this account.');
        abort_if($organization->status === OrganizationStatus::Disabled, 403, 'This organization has been disabled.');

        $this->tenant->set($organization);

        return $next($request);
    }
}
