<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReembedStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Jobs\DropProjectCollection;
use App\Jobs\ReembedProject;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->withCount('documents')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): ProjectResource
    {
        $project = Project::create($request->validated());

        return new ProjectResource($project->loadCount('documents'));
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->loadCount('documents'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        abort_if(
            $project->isReembedding(),
            409,
            'This project is re-embedding its documents. Try again once it finishes.',
        );

        $validated = $request->validated();

        $modelChanged = array_key_exists('embedder_model', $validated)
            && $validated['embedder_model'] !== $project->embedding_model_id;

        $project->update($validated);

        // Only rebuild when documents were already embedded under the old model;
        // otherwise the next ProcessDocument run binds the new one for free.
        if ($modelChanged && $project->embedding_model_id !== null) {
            $project->forceFill([
                'reembed_status' => ReembedStatus::Queued,
                'reembed_error' => null,
            ])->save();

            ReembedProject::dispatch($project);
        }

        return new ProjectResource($project->fresh()->loadCount('documents'));
    }

    public function destroy(Project $project): Response
    {
        $hadEmbeddings = $project->embedding_model_id !== null;
        $collection = $project->vectorCollection();

        $project->delete();

        if ($hadEmbeddings) {
            DropProjectCollection::dispatch($collection);
        }

        return response()->noContent();
    }
}
