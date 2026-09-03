<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Jobs\PurgeDocumentVectors;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $documents = $project->documents()
            ->with('uploadedBy')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request, Project $project): JsonResponse
    {
        $disk = config('raas.documents.disk');
        $directory = "organizations/{$project->organization_id}/projects/{$project->id}";

        $ids = collect($request->file('files'))->map(function ($file) use ($request, $project, $disk, $directory) {
            $path = $file->store($directory, $disk);

            return $project->documents()->create([
                'uploaded_by_user_id' => $request->user()->id,
                'original_filename' => $file->getClientOriginalName(),
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'status' => DocumentStatus::Uploaded,
            ])->id;
        });

        $documents = $project->documents()
            ->with('uploadedBy')
            ->whereKey($ids)
            ->orderByDesc('id')
            ->get();

        return DocumentResource::collection($documents)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('uploadedBy'));
    }

    public function destroy(Document $document): Response
    {
        $project = $document->project;
        $documentId = $document->id;

        $document->delete();

        if ($project->embedding_model_id !== null) {
            PurgeDocumentVectors::dispatch($documentId, $project->vectorCollection());
        }

        return response()->noContent();
    }
}
