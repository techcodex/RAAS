<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\RagClient;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectExportController extends Controller
{
    /**
     * Stream the project's embeddings as NDJSON: a manifest line (model, dimension,
     * distance, count) followed by one JSON object per vector `{id, vector, payload}`.
     * The bundle can be re-imported into the customer's own Qdrant or pgvector.
     */
    public function show(Project $project, RagClient $rag): StreamedResponse
    {
        abort_if(
            $project->embedding_model_id === null,
            409,
            'This project has no embeddings yet. Process at least one document first.',
        );

        $upstream = $rag->export($project->vectorCollection());
        $body = $upstream->toPsrResponse()->getBody();
        $filename = "{$project->vectorCollection()}-embeddings.ndjson";

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
