<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RagException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QueryRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Project;
use App\Services\RagPipeline;
use Illuminate\Http\JsonResponse;

class QueryController extends Controller
{
    public function store(QueryRequest $request, Project $project, RagPipeline $pipeline): JsonResponse
    {
        $credential = $project->credential;
        abort_if($credential === null, 422, 'Add an LLM API key to this project before asking questions.');

        $conversation = $request->filled('conversation_id')
            ? $project->conversations()->findOrFail($request->integer('conversation_id'))
            : null;

        try {
            $message = $pipeline->ask(
                $project,
                $credential,
                $request->string('question'),
                $conversation,
                $request->integer('top_k') ?: null,
            );
        } catch (RagException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new MessageResource($message))
            ->additional(['conversation_id' => $message->conversation_id])
            ->response();
    }
}
