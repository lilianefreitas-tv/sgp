<?php

namespace App\Policies;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function changeContext(User $user, Organization $organization): bool
    {
        return $organization->status === OrganizationStatus::Active
            && $user->organizationMemberships()
                ->where('organization_id', $organization->id)
                ->where('status', OrganizationMembershipStatus::Active->value)
                ->exists();
    }
}
