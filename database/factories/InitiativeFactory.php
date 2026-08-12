<?php

namespace Database\Factories;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\InitiativeOrigin;
use App\Enums\InitiativeState;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Initiative> */
class InitiativeFactory extends Factory
{
    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'code' => 'INI-'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(4), 'context' => fake()->paragraph(), 'origin' => InitiativeOrigin::Internal,
            'state' => InitiativeState::Draft, 'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable,
            'management_level' => ManagementLevel::Essential, 'methodology' => ProjectMethodology::Kanban,
            'created_by' => User::factory()];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function withActor(User $actor): static
    {
        return $this->state(fn () => ['created_by' => $actor->id]);
    }

    public function withActiveMembership(): static
    {
        return $this->afterCreating(function (Initiative $initiative): void {
            OrganizationMembership::query()->firstOrCreate([
                'organization_id' => $initiative->organization_id,
                'user_id' => $initiative->created_by,
            ], ['role_code' => 'member', 'status' => 'active', 'is_default' => false, 'joined_at' => now()]);
        });
    }
}
