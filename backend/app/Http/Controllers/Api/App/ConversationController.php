<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Resources\Api\App\AppConversationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $this->employeePublication($request, $slug);

        $conversations = $request->user()->conversations()
            ->orderByDesc('updated_at')
            ->paginate(50);

        return AppConversationResource::collection($conversations);
    }

    public function show(Request $request, string $slug, int $conversation): AppConversationResource
    {
        $this->employeePublication($request, $slug);

        $model = $request->user()->conversations()
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->findOrFail($conversation);

        return new AppConversationResource($model);
    }
}
