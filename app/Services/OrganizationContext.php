<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Support\Collection;

class OrganizationContext
{
    private ?OrganizationMembership $membership = null;

    /** @var Collection<int, OrganizationMembership>|null */
    private ?Collection $availableMemberships = null;

    /** @param Collection<int, OrganizationMembership> $availableMemberships */
    public function activate(
        OrganizationMembership $membership,
        Collection $availableMemberships,
    ): void
    {
        $this->membership = $membership->loadMissing('organization');
        $this->availableMemberships = $availableMemberships;
    }

    public function clear(): void
    {
        $this->membership = null;
        $this->availableMemberships = null;
    }

    public function active(): bool
    {
        return $this->membership !== null;
    }

    public function id(): ?int
    {
        return $this->membership === null
            ? null
            : (int) $this->membership->organization_id;
    }

    public function organization(): ?Organization
    {
        return $this->membership?->organization;
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
}
