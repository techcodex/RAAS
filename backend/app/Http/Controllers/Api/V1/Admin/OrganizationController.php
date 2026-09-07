<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreOrganizationRequest;
use App\Http\Requests\Api\V1\Admin\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\Admin\AdminOrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

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

    public function store(StoreOrganizationRequest $request): AdminOrganizationResource
    {
        $name = $request->string('name')->toString();

        $organization = Organization::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'owner_id' => $request->user()->id,
            'document_limit' => $request->input('document_limit'),
        ]);

        return new AdminOrganizationResource(
            $organization->load('owner')->loadCount(['members', 'projects', 'documents']),
        );
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): AdminOrganizationResource
    {
        $organization->update($request->validated());

        return new AdminOrganizationResource(
            $organization->load('owner')->loadCount(['members', 'projects', 'documents']),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Organization::where('slug', $slug)->exists());

        return $slug;
    }
}
