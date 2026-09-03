<?php

namespace App\Jobs;

use App\Services\RagClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drops a deleted project's Qdrant collection. Dispatched from ProjectController::destroy.
 */
class DropProjectCollection implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $collection) {}

    public function handle(RagClient $rag): void
    {
        $rag->dropCollection($this->collection);
    }
}
