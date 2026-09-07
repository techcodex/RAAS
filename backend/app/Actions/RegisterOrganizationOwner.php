<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterOrganizationOwner
{
    /**
     * Create a user together with the personal organization they own, and make
     * that organization their current tenant.
     *
     * @param  array{name: string, email: string, password: string, organization_name?: string|null}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $orgName = $data['organization_name'] ?? "{$data['name']}'s Organization";

            $organization = Organization::create([
                'name' => $orgName,
                'slug' => Organization::generateUniqueSlug($orgName),
                'owner_id' => $user->id,
            ]);

            $organization->members()->attach($user->id, ['role' => 'owner']);

            $user->forceFill(['current_organization_id' => $organization->id])->save();

            return $user->load('currentOrganization');
        });
    }
}
