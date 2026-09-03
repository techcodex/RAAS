<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RagClient;
use Illuminate\Support\Facades\Cache;

class StrategyController extends Controller
{
    /**
     * Chunking strategies + embedders offered by the rag-service, with their
     * JSON-schema config. Cached briefly since it only changes on deploy.
     */
    public function index(RagClient $rag): array
    {
        return Cache::remember('rag:strategies', now()->addMinutes(5), fn () => $rag->strategies());
    }
}
