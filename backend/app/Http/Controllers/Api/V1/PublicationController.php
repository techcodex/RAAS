<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePublicationRequest;
use App\Http\Resources\Api\V1\PublicationResource;
use App\Models\AppPublication;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Owner-side management of a project's employee-app publication. One per project.
 */
class PublicationController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $publication = $project->publication()->withCount('users')->first();

        return response()->json([
            'data' => $publication ? new PublicationResource($publication) : null,
        ]);
    }

    public function update(UpdatePublicationRequest $request, Project $project): JsonResponse
    {
        $validated = $request->validated();
        $publication = $project->publication;
        $created = $publication === null;

        if ($publication === null) {
            $publication = new AppPublication([
                'welcome_message' => $validated['welcome_message'] ?? null,
                'suggested_questions' => $validated['suggested_questions'] ?? null,
                'daily_query_limit' => $validated['daily_query_limit'] ?? null,
                'is_active' => false,
            ]);
            $publication->forceFill([
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'slug' => AppPublication::generateUniqueSlug($project->name),
            ]);
        } else {
            $publication->fill($validated);
        }

        if (($validated['is_active'] ?? $publication->is_active) === true) {
            $this->assertPublishable($project);
            $publication->is_active = true;
        }

        $publication->save();

        return (new PublicationResource($publication->loadCount('users')))
            ->response()
            ->setStatusCode($created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    private function assertPublishable(Project $project): void
    {
        abort_if(
            $project->embedding_model_id === null,
            422,
            'Process at least one document before publishing this project.',
        );
        abort_if(
            $project->credential === null,
            422,
            'Add an LLM API key to this project before publishing it.',
        );
    }
}
