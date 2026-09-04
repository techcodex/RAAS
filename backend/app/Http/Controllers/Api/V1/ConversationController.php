<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ConversationController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $conversations = $project->conversations()
            ->orderByDesc('updated_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        return new ConversationResource(
            $conversation->load(['messages' => fn ($q) => $q->orderBy('id')])
        );
    }

    public function destroy(Conversation $conversation): Response
    {
        $conversation->delete();

        return response()->noContent();
    }
}
