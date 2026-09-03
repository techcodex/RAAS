<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChunkResource;
use App\Models\Document;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChunkController extends Controller
{
    /**
     * Preview the chunks produced for a document (mirrored from the rag-service).
     */
    public function index(Document $document): AnonymousResourceCollection
    {
        $chunks = $document->chunks()
            ->orderBy('chunk_index')
            ->paginate(50);

        return ChunkResource::collection($chunks);
    }
}
