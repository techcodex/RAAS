<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProcessDocumentRequest;
use App\Http\Resources\Api\V1\DocumentResource;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

/**
 * Queues (or re-queues) a document through the chunk → embed pipeline.
 * Modelled as its own resource: POST creates a processing run.
 */
class DocumentProcessingController extends Controller
{
    public function store(ProcessDocumentRequest $request, Document $document): JsonResponse
    {
        $document->update([
            'status' => DocumentStatus::Queued,
            'error_message' => null,
        ]);

        ProcessDocument::dispatch(
            $document,
            $request->input('strategy'),
            $request->input('strategy_config'),
        );

        return DocumentResource::make($document->fresh())
            ->response()
            ->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }
}
