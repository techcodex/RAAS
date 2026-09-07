<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\RegisterOrganizationOwner;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreOrganizationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrganizationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrganizationStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class OrganizationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $organizations = Organization::query()
            ->with('owner')
            ->withCount(['members', 'projects', 'documents'])
            ->orderBy('name')
            ->paginate(30);

        return AdminOrganizationResource::collection($organizations);
    }

    /**
     * Create an organization together with its owner account. The generated
     * password is returned once, alongside the resource, for the admin to hand
     * off — it is never retrievable again.
     */
    public function store(StoreOrganizationRequest $request, RegisterOrganizationOwner $register): JsonResponse
    {
        $password = Str::password(16);

        $organization = DB::transaction(function () use ($request, $register, $password) {
            $owner = $register->handle([
                'name' => $request->string('owner_name')->toString(),
                'email' => $request->string('owner_email')->toString(),
                'password' => $password,
                'organization_name' => $request->string('name')->toString(),
            ]);

            $organization = $owner->currentOrganization;

            if ($request->filled('document_limit')) {
                $organization->update(['document_limit' => $request->integer('document_limit')]);
            }

            return $organization;
        });

        return $this->resource($organization)
            ->additional(['temporary_password' => $password])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): AdminOrganizationResource
    {
        $organization->update($request->validated());

        return $this->resource($organization);
    }

    /**
     * Enable or disable an organization. Disabling ends its members' sessions
     * immediately (platform admins excluded) and blocks them from signing in.
     */
    public function updateStatus(UpdateOrganizationStatusRequest $request, Organization $organization): AdminOrganizationResource
    {
        $status = $request->enum('status', OrganizationStatus::class);

        DB::transaction(function () use ($organization, $status) {
            // `status` is not mass-assignable — transitions only happen here.
            $organization->forceFill(['status' => $status])->save();

            if ($status->isDisabled()) {
                $this->revokeMemberTokens($organization);
            }
        });

        return $this->resource($organization);
    }

    /**
     * Delete every API token belonging to the organization's non-admin members
     * so a disable takes effect on the next request.
     */
    private function revokeMemberTokens(Organization $organization): void
    {
        $memberIds = $organization->members()->pluck('users.id')
            ->push($organization->owner_id)
            ->unique();

        $nonAdminIds = User::whereIn('id', $memberIds)
            ->where('is_admin', false)
            ->pluck('id');

        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $nonAdminIds)
            ->delete();
    }

    private function resource(Organization $organization): AdminOrganizationResource
    {
        return new AdminOrganizationResource(
            $organization->load('owner')->loadCount(['members', 'projects', 'documents']),
        );
    }
}
