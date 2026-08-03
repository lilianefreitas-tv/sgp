<?php

namespace Tests;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null): static
    {
        if ($user instanceof User
            && Schema::hasTable('organization_memberships')
            && ! $user->organizationMemberships()->exists()) {
            $organization = Organization::query()
                ->where('status', OrganizationStatus::Active->value)
                ->orderBy('id')
                ->first();

            if ($organization !== null) {
                $user->organizationMemberships()->create([
                    'organization_id' => $organization->id,
                    'role_code' => $user->isAdministrator()
                        ? OrganizationRole::Administrator
                        : OrganizationRole::Member,
                    'status' => OrganizationMembershipStatus::Active,
                    'is_default' => true,
                    'joined_at' => now(),
                ]);
            }
        }

        return parent::actingAs($user, $guard);
    }
}
