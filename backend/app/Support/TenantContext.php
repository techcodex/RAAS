<?php

namespace App\Support;

use App\Models\Organization;

/**
 * Holds the organization for the current request (or job). Registered as a
 * singleton and populated by the ResolveTenant middleware; the
 * BelongsToOrganization trait reads it to scope every tenant-owned query.
 */
class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function forget(): void
    {
        $this->organization = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function getOrFail(): Organization
    {
        if ($this->organization === null) {
            throw new \RuntimeException('No tenant is bound to the current context.');
        }

        return $this->organization;
    }
}
