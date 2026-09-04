<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCredentialRequest;
use App\Http\Resources\Api\V1\ProjectCredentialResource;
use App\Models\Project;
use App\Support\LlmProviders;
use Illuminate\Http\Response;

class ProjectCredentialController extends Controller
{
    public function show(Project $project): ProjectCredentialResource|Response
    {
        return $project->credential
            ? new ProjectCredentialResource($project->credential)
            : response()->noContent(204);
    }

    public function store(StoreCredentialRequest $request, Project $project): ProjectCredentialResource
    {
        $provider = $request->string('provider')->toString();

        $credential = $project->credential()->updateOrCreate([], [
            'provider' => $provider,
            'api_key' => $request->string('api_key'),
            'model' => $request->input('model') ?? LlmProviders::defaultModelFor($provider),
        ]);

        return new ProjectCredentialResource($credential);
    }

    public function destroy(Project $project): Response
    {
        $project->credential()->delete();

        return response()->noContent();
    }
}
