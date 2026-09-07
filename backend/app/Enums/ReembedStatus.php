<?php

namespace App\Enums;

enum ReembedStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Failed = 'failed';

    /**
     * Whether a re-embed is still in flight (queued or running).
     */
    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
