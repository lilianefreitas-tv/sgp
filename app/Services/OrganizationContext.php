<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Collection;

class OrganizationContext
{
    private ?OrganizationMembership $membership = null;

    private ?Organization $platformOrganization = null;

    private bool $platformAccess = false;

    /** @var Collection<int, OrganizationMembership>|null */
    private ?Collection $availableMemberships = null;

    /** @param Collection<int, OrganizationMembership> $availableMemberships */
    public function activate(
        OrganizationMembership $membership,
        Collection $availableMemberships,
    ): void
    {
        $this->membership = $membership->loadMissing('organization');
        $this->platformOrganization = null;
        $this->platformAccess = false;
        $this->availableMemberships = $availableMemberships;
    }

    /** @param Collection<int, OrganizationMembership> $availableMemberships */
    public function activatePlatformAccess(
        Organization $organization,
        Collection $availableMemberships,
    ): void {
        $this->membership = null;
        $this->platformOrganization = $organization;
        $this->platformAccess = true;
        $this->availableMemberships = $availableMemberships;
    }

    public function clear(): void
    {
        $this->membership = null;
        $this->platformOrganization = null;
        $this->platformAccess = false;
        $this->availableMemberships = null;
    }

    public function active(): bool
    {
        return $this->membership !== null || $this->platformOrganization !== null;
    }

    public function id(): ?int
    {
        if ($this->membership !== null) {
            return (int) $this->membership->organization_id;
        }

        return $this->platformOrganization?->id;
    }

    public function organization(): ?Organization
    {
        return $this->membership?->organization ?? $this->platformOrganization;
    }

    public function membership(): ?OrganizationMembership
    {
        return $this->membership;
    }

    /** @return Collection<int, OrganizationMembership> */
    public function availableMemberships(): Collection
    {
        return $this->availableMemberships ?? collect();
    }

    public function role(): ?OrganizationRole
    {
        return $this->membership?->role_code;
    }

    public function isPlatformAccess(): bool
    {
        return $this->platformAccess;
    }
}
