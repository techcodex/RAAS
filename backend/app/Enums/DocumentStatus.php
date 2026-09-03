<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Chunking = 'chunking';
    case Embedding = 'embedding';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * Whether the document is still moving through the processing pipeline.
     */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Queued, self::Chunking, self::Embedding], true);
    }
}
