<?php

namespace App\Http\Controllers\Api\App;

use App\Exceptions\RagException;
use App\Http\Requests\Api\App\StoreQueryRequest;
use App\Http\Resources\Api\App\AppMessageResource;
use App\Models\AppMessage;
use App\Models\AppPublication;
use App\Services\RagPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QueryController extends Controller
{
    public function store(StoreQueryRequest $request, string $slug, RagPipeline $pipeline): JsonResponse
    {
        $publication = $this->employeePublication($request, $slug);
        $project = $publication->project;
        $user = $request->user();

        abort_if($project->credential === null, 422, 'This app is not ready for questions yet.');
        $this->enforceDailyLimit($publication, $user->id);

        $conversation = $request->filled('conversation_id')
            ? $user->conversations()->findOrFail($request->integer('conversation_id'))
            : null;

        $question = $request->string('question')->toString();

        $history = $conversation
            ? $conversation->messages()->orderBy('id')->get()
                ->map(fn (AppMessage $m) => ['role' => $m->role, 'content' => $m->content])->all()
            : [];

        try {
            $result = $pipeline->answer($project, $project->credential, $question, $history);
        } catch (RagException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $conversation ??= $user->conversations()->create([
            'app_publication_id' => $publication->id,
            'title' => Str::limit($question, 60),
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => $question]);
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result->answer->text,
            'citations' => $result->citations,
        ]);

        $user->forceFill(['last_seen_at' => now()])->save();

        return (new AppMessageResource($assistant))
            ->additional(['conversation_id' => $conversation->id])
            ->response();
    }

    private function enforceDailyLimit(AppPublication $publication, int $appUserId): void
    {
        if ($publication->daily_query_limit === null) {
            return;
        }

        $askedToday = AppMessage::query()
            ->where('role', 'user')
            ->whereDate('created_at', today())
            ->whereHas('conversation', fn ($q) => $q->where('app_user_id', $appUserId))
            ->count();

        abort_if(
            $askedToday >= $publication->daily_query_limit,
            429,
            "You've reached today's question limit for this app.",
        );
    }
}
