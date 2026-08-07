<?php

namespace Database\Factories;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationMembership> */
class OrganizationMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role_code' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
            'is_default' => false,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role_code' => OrganizationRole::Owner,
            'is_default' => true,
        ]);
    }
}
