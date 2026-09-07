<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Whether the organization is disabled — its users cannot sign in or reach
     * any tenant-scoped route.
     */
    public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }
}
