<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\AppUserResource;
use App\Models\AppPublication;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Owner-side management of the employees who may sign in to a project's
 * published app.
 */
class EmployeeController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        return AppUserResource::collection(
            $this->publication($project)->users()->orderBy('name')->get(),
        );
    }

    public function store(StoreEmployeeRequest $request, Project $project): JsonResponse
    {
        $code = $request->filled('access_code')
            ? $request->string('access_code')->toString()
            : $this->generateCode();

        $employee = $this->publication($project)->users()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'access_code' => $code,
            'is_active' => true,
        ]);

        return (new AppUserResource($employee))
            ->additional(['access_code' => $code])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateEmployeeRequest $request, Project $project, int $employee): AppUserResource
    {
        $model = $this->publication($project)->users()->findOrFail($employee);
        $model->update($request->validated());

        return new AppUserResource($model);
    }

    public function destroy(Project $project, int $employee): Response
    {
        $this->publication($project)->users()->findOrFail($employee)->delete();

        return response()->noContent();
    }

    public function resetCode(Project $project, int $employee): JsonResponse
    {
        $model = $this->publication($project)->users()->findOrFail($employee);
        $code = $this->generateCode();
        $model->update(['access_code' => $code]);

        return (new AppUserResource($model))
            ->additional(['access_code' => $code])
            ->response();
    }

    private function publication(Project $project): AppPublication
    {
        $publication = $project->publication;
        abort_if($publication === null, 404, 'This project has not been published.');

        return $publication;
    }

    /**
     * An 8-character code from an unambiguous alphabet (no 0/O, 1/I/L).
     */
    private function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
