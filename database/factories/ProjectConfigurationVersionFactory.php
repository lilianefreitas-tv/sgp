<?php

namespace Database\Factories;

use App\Enums\ExecutionNature;
use App\Enums\FinancialManagementMode;
use App\Enums\ManagementLevel;
use App\Enums\ProjectMethodology;
use App\Models\Project;
use App\Models\ProjectConfigurationVersion;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectConfigurationVersion> */
class ProjectConfigurationVersionFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'sequence' => 1, 'execution_nature' => ExecutionNature::Internal,
            'financial_management_mode' => FinancialManagementMode::NotApplicable, 'management_level' => ManagementLevel::Essential,
            'methodology' => ProjectMethodology::Kanban, 'effective_from' => now(), 'changed_by' => User::factory(),
            'justification' => 'Configuração inicial', 'recorded_at' => now()];
    }

    public function withActiveMembership(): static
    {
        return $this->afterCreating(function (ProjectConfigurationVersion $version): void {
            OrganizationMembership::query()->firstOrCreate([
                'organization_id' => $version->organization_id,
                'user_id' => $version->changed_by,
            ], ['role_code' => 'member', 'status' => 'active', 'is_default' => false, 'joined_at' => now()]);
        });
    }
}
